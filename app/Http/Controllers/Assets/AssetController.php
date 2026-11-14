<?php

namespace App\Http\Controllers\Assets;

use App\Accounting\Assets;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AssetCategory;
use App\Models\AssetEvent;
use App\Models\AuditLog;
use App\Models\FixedAsset;
use App\Support\Dec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Asset register, depreciation runs, maintenance and transfer log, disposals. */
class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['active', 'disposed'], true) ? $request->query('status') : null;
        $term = trim((string) $request->query('q', ''));
        $active = FixedAsset::where('status', 'active');

        return view('assets.index', [
            'status' => $status,
            'term' => $term,
            'assets' => FixedAsset::with('category')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('name', 'like', '%'.$term.'%')->orWhere('location', 'like', '%'.$term.'%')->orWhere('assigned_to', 'like', '%'.$term.'%')))
                ->orderBy('number')->paginate(per_page(25))->withQueryString(),
            'cost' => Dec::round((clone $active)->sum('cost')),
            'depreciated' => Dec::round((clone $active)->sum('depreciated')),
            // the month offered for the next run: the current one
            'period' => substr(today_iso(), 0, 7),
        ]);
    }

    public function create(): View
    {
        return view('assets.form', [
            'categories' => AssetCategory::orderBy('name')->get(),
            'payAccounts' => Account::postable()->whereIn('kind', ['cash', 'bank', 'supplier'])->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', base_currency()))->orderBy('kind')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $asset = Assets::acquire(company(), $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'asset_category_id' => ['nullable', 'integer', own('asset_categories')],
            'acquired_on' => ['required', 'date', 'before_or_equal:today'],
            'cost' => ['required', 'numeric', 'gt:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'paid_from_account_id' => ['nullable', 'integer', own('accounts')],
            'location' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));

        return redirect()->route('assets.show', ['id' => $asset->id])->with('success', __(':code saved.', ['code' => $asset->code()]));
    }

    public function show(Request $request): View
    {
        $asset = FixedAsset::with('category', 'depreciations', 'events')->findOrFail($request->route('id'));

        return view('assets.show', [
            'asset' => $asset,
            'cashAccounts' => Account::postable()->cashOrBank()->where('currency', base_currency())->orderBy('name')->get(),
        ]);
    }

    /** Run depreciation for a month. Running the same month again changes nothing. */
    public function depreciate(Request $request): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $result = Assets::depreciate(company(), $data['period']);
        AuditLog::record('ran depreciation', $data['period'], $result);

        return back()->with($result['assets'] ? 'success' : 'info', $result['assets']
            ? __('Depreciation of :period posted: :amount on :count assets.', ['period' => $data['period'], 'amount' => money($result['amount']), 'count' => $result['assets']])
            : __('Nothing to depreciate for :period. It was already run, or no asset is due.', ['period' => $data['period']]));
    }

    public function dispose(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'proceeds' => ['nullable', 'numeric', 'min:0'],
            'account_id' => ['nullable', 'integer', own('accounts')],
        ]);
        $asset = Assets::dispose(FixedAsset::findOrFail($request->route('id')), $data['date'], $data['proceeds'] ?? '0', isset($data['account_id']) ? (int) $data['account_id'] : null);
        AuditLog::record('disposed asset', $asset->name, ['proceeds' => $asset->disposal_proceeds]);

        return back()->with('success', __(':code disposed of.', ['code' => $asset->code()]));
    }

    /** A maintenance note or a move to another place or person. Details of the asset change with a move. */
    public function event(Request $request): RedirectResponse
    {
        $asset = FixedAsset::findOrFail($request->route('id'));
        $data = $request->validate([
            'type' => ['required', Rule::in(['maintenance', 'transfer'])],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
        ]);
        AssetEvent::create(['fixed_asset_id' => $asset->id, 'type' => $data['type'], 'date' => $data['date'], 'description' => $data['description'], 'cost' => $data['cost'] ?? null]);
        if ($data['type'] === 'transfer') {
            $asset->update(array_filter(['location' => $data['location'] ?? null, 'assigned_to' => $data['assigned_to'] ?? null], fn ($value) => $value !== null));
        }

        return back()->with('success', __('Saved.'));
    }
}
