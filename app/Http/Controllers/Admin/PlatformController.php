<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Module;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Dec;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** The smaller screens of the platform side: users, administrators, audit log, revenue, module entitlements, system health. */
class PlatformController extends Controller
{
    /* ------------------------------------------------------------ users */

    public function users(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('admin.users', [
            'term' => $term,
            'users' => User::withCount('companies')
                ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
                ->latest('id')->paginate(per_page(25))->withQueryString(),
        ]);
    }

    /** Stop a person from signing in anywhere, or let him in again. His records stay. */
    public function toggleUser(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->canDecide(), 403);
        $user = User::findOrFail($request->route('id'));
        $user->update(['is_active' => ! $user->is_active]);
        AuditLog::record($user->is_active ? 'restored user' : 'blocked user', $user->email);

        return back()->with('success', __(':name saved.', ['name' => $user->name]));
    }

    /* ------------------------------------------------------------ administrators */

    public function admins(): View
    {
        return view('admin.admins', ['admins' => Admin::orderBy('name')->get()]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->isSuper(), 403);
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:admins,email'],
            'role' => ['required', Rule::in(array_keys(Admin::ROLES))],
            'password' => ['required', Password::min(10)],
        ]);
        $admin = Admin::create($data);
        AuditLog::record('added administrator', $admin->email, ['role' => $admin->role]);

        return back()->with('success', __('Administrator added.'));
    }

    public function updateAdmin(Request $request): RedirectResponse
    {
        $me = $request->user('admin');
        abort_unless($me->isSuper(), 403);
        $admin = Admin::findOrFail($request->route('id'));
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(Admin::ROLES))], 'is_active' => ['required', 'boolean']]);

        // the platform must always keep one active super administrator, and nobody locks himself out
        $losesSuper = ($admin->role ?? 'super') === 'super' && ($data['role'] !== 'super' || ! $data['is_active']);
        if ($losesSuper && ($admin->id === $me->id || Admin::where('role', 'super')->where('is_active', true)->where('id', '!=', $admin->id)->doesntExist())) {
            return back()->with('danger', __('There must always be an active super administrator, and you cannot remove your own access.'));
        }
        $admin->update(['role' => $data['role'], 'is_active' => (bool) $data['is_active']]);
        AuditLog::record('changed administrator', $admin->email, $data);

        return back()->with('success', __('Administrator saved.'));
    }

    /* ------------------------------------------------------------ audit log */

    public function audit(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('admin.audit', [
            'term' => $term,
            'only' => $request->query('only'),
            'logs' => AuditLog::query()
                // "platform": what administrators did. Without it, everything, company actions included (action and subject only).
                ->when($request->query('only') === 'platform', fn ($q) => $q->whereNotNull('admin_id'))
                ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('action', 'like', '%'.$term.'%')->orWhere('subject', 'like', '%'.$term.'%')))
                ->latest('id')->paginate(per_page(50))->withQueryString(),
            'admins' => Admin::pluck('name', 'id'),
            'companies' => Company::pluck('name', 'id'),
        ]);
    }

    /* ------------------------------------------------------------ revenue */

    public function revenue(Request $request): View
    {
        $year = min(2100, max(2020, $request->integer('year') ?: (int) date('Y')));
        $payments = SubscriptionPayment::with('plan')->where('status', SubscriptionPayment::APPROVED)->whereYear('reviewed_at', $year)->get();

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = ['setup' => '0', 'annual' => '0', 'count' => 0];
        }
        $plans = [];
        foreach ($payments as $payment) {
            $month = (int) $payment->reviewed_at->format('n');
            $months[$month]['setup'] = Dec::add($months[$month]['setup'], $payment->setup_fee, 2);
            $months[$month]['annual'] = Dec::add($months[$month]['annual'], $payment->annual_fee, 2);
            $months[$month]['count']++;
            $name = $payment->plan?->name ?? '—';
            $plans[$name] = Dec::add($plans[$name] ?? '0', $payment->amount, 2);
        }

        return view('admin.revenue', [
            'year' => $year,
            'months' => $months,
            'plans' => $plans,
            'setup' => Dec::sum(array_column($months, 'setup'), 2),
            'annual' => Dec::sum(array_column($months, 'annual'), 2),
            'currency' => config('billing.currency'),
            // what next year's renewals are worth if every active company renews
            'renewals' => Dec::round(Company::where('status', Company::ACTIVE)->where('subscription_ends_at', '>=', now()->toDateString())->join('plans', 'plans.id', '=', 'companies.plan_id')->sum('plans.annual_fee'), 2),
        ]);
    }

    /* ------------------------------------------------------------ module entitlements */

    public function modules(): View
    {
        return view('admin.modules', ['definitions' => Modules::all(), 'rows' => Module::all()->keyBy('key'), 'plans' => Plan::orderBy('sort')->get()]);
    }

    /**
     * Which versions include each Business module, and whether the module is offered at all.
     * Companies get what their version includes, so this is applied to every company at once.
     */
    public function updateModules(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->isSuper(), 403);
        $codes = Plan::pluck('code')->all();
        $data = $request->validate([
            'available' => ['nullable', 'array'],
            'plans' => ['nullable', 'array'],
            'plans.*' => ['array'],
            'plans.*.*' => [Rule::in($codes)],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_keys(Modules::all()) as $key) {
                Module::updateOrCreate(['key' => $key], ['is_available' => ! empty($data['available'][$key]), 'plans' => array_values($data['plans'][$key] ?? [])]);
            }
            // every company is brought in line with what its version now includes (dependencies are kept by Modules::set)
            Company::with('plan')->orderBy('id')->chunkById(200, function ($companies) {
                foreach ($companies as $company) {
                    Modules::set($company, Modules::entitled($company));
                }
            });
            AuditLog::record('changed module entitlements', null, ['available' => array_keys($data['available'] ?? []), 'plans' => $data['plans'] ?? []]);
        });

        return back()->with('success', __('Entitlements saved and applied to every company.'));
    }

    /* ------------------------------------------------------------ system health */

    public function health(): View
    {
        $checks = [];
        try {
            DB::select('select 1');
            $checks[] = [__('Database'), true, config('database.default')];
        } catch (\Throwable $e) {
            $checks[] = [__('Database'), false, $e->getMessage()];
        }
        try {
            Storage::disk('local')->put('health.txt', 'ok');
            $checks[] = [__('Private file storage'), Storage::disk('local')->get('health.txt') === 'ok', __('Receipts and attachments are kept here')];
            Storage::disk('local')->delete('health.txt');
        } catch (\Throwable $e) {
            $checks[] = [__('Private file storage'), false, $e->getMessage()];
        }
        $checks[] = [__('Exact decimal arithmetic (bcmath)'), extension_loaded('bcmath'), __('Needed for every amount')];
        $checks[] = [__('Debug mode off'), ! config('app.debug'), config('app.debug') ? __('Switch APP_DEBUG off in production') : ''];
        $checks[] = [__('Email verification'), (bool) config('billing.verify_email'), config('billing.verify_email') ? '' : __('Off (EMAIL_VERIFICATION=false). Switch it on in production.')];
        $checks[] = [__('Mail'), config('mail.default') !== 'log', __('Mailer: :mailer', ['mailer' => config('mail.default')])];

        return view('admin.health', [
            'checks' => $checks,
            'facts' => [
                __('Application') => config('app.name').' · '.app()->version(),
                'PHP' => PHP_VERSION,
                __('Environment') => app()->environment(),
                __('Queue') => config('queue.default'),
                __('Cache') => config('cache.default'),
                __('Time zone') => config('app.timezone'),
                __('Payments waiting') => (string) SubscriptionPayment::where('status', SubscriptionPayment::PENDING)->count(),
                __('Scheduler') => __('The server must run "php artisan schedule:run" every minute for recurring transactions.'),
            ],
        ]);
    }
}
