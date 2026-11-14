<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Inventory\Pos;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** The selling screen, cashier sessions, POS sales and cash closing. */
class PosController extends Controller
{
    /** Open POS: the registers, each with its open session or an "open" form. */
    public function index(): View
    {
        return view('pos.index', [
            'registers' => PosRegister::with('warehouse', 'cashAccount')->where('is_active', true)->orderBy('name')->get(),
            'sessions' => PosSession::with('user')->where('status', 'open')->get()->keyBy('pos_register_id'),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate(['opening_cash' => ['nullable', 'numeric', 'min:0']]);
        $session = Pos::open(PosRegister::findOrFail($request->route('id')), $data['opening_cash'] ?? '0');

        return redirect()->route('pos.sell', ['id' => $session->id]);
    }

    /** The touch-friendly selling screen of an open session. */
    public function sell(Request $request): View|RedirectResponse
    {
        $session = PosSession::with('register')->findOrFail($request->route('id'));
        if (! $session->isOpen()) {
            return redirect()->route('pos.session', ['id' => $session->id]);
        }

        $products = Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'barcode', 'sale_price', 'tax_rate', 'type', 'track_stock']);
        $stock = StockLevel::where('warehouse_id', $session->register->warehouse_id)->pluck('quantity', 'product_id');

        return view('pos.sell', [
            'session' => $session,
            'products' => $products,
            'stock' => $stock,
            'customers' => Account::postable()->where('kind', 'customer')->orderBy('name')->get(['id', 'name']),
            // the drawer first, then the other cash and bank accounts in the base currency (card machine, mobile money ...)
            'payAccounts' => Account::postable()->cashOrBank()->where('currency', base_currency())->orderByRaw('id = ? DESC', [$session->register->cash_account_id])->orderBy('name')->get(['id', 'name', 'kind']),
            'once' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $session = PosSession::findOrFail($request->route('id'));
        $data = $request->validate([
            'once' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'integer', own('accounts')],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', own('products')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.account_id' => ['required', 'integer', own('accounts')],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        // only a role that may manage sales can change a price or give a discount at the till
        if (! can_do('sales.manage')) {
            $prices = Product::whereIn('id', array_column($data['items'], 'product_id'))->pluck('sale_price', 'id');
            foreach ($data['items'] as $i => $item) {
                $data['items'][$i]['unit_price'] = $prices[$item['product_id']] ?? $item['unit_price'];
                $data['items'][$i]['discount'] = '0';
            }
        }
        $sale = Pos::sell($session, array_values($data['items']), array_values(array_filter($data['payments'], fn ($p) => ($p['amount'] ?? '') !== '' && $p['amount'] !== null)), $data['customer_id'] ?? null, $data['once']);

        return redirect()->route('pos.sale', ['id' => $sale->id])->with('success', __(':code paid.', ['code' => $sale->code()]));
    }

    /** The receipt of a POS sale. */
    public function sale(Request $request): View
    {
        $sale = PosSale::with('session.register', 'customer', 'invoice')->findOrFail($request->route('id'));

        return view('pos.sale', [
            'sale' => $sale,
            // a refund needs an open drawer: this cashier's open session, if he has one
            'openSession' => PosSession::where('status', 'open')->where('user_id', auth()->id())->first() ?? PosSession::where('status', 'open')->where('pos_register_id', $sale->session->pos_register_id)->first(),
        ]);
    }

    public function refund(Request $request): RedirectResponse
    {
        $sale = PosSale::findOrFail($request->route('id'));
        $session = PosSession::where('status', 'open')->findOrFail($request->integer('session_id'));
        Pos::refund($sale, $session);
        AuditLog::record('refunded '.$sale->code());

        return back()->with('success', __(':code refunded.', ['code' => $sale->code()]));
    }

    /** POS sales and returns, newest first. */
    public function sales(Request $request): View
    {
        return view('pos.sales', [
            'sales' => PosSale::with('customer', 'session.register')
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->when(query_date('from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                ->when(query_date('until'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                ->orderByDesc('id')->paginate(per_page(25))->withQueryString(),
        ]);
    }

    /** Cashier sessions: open and closed, with expected and counted cash. */
    public function sessions(): View
    {
        return view('pos.sessions', ['sessions' => PosSession::with('register', 'user')->orderByDesc('id')->paginate(per_page(25))]);
    }

    /** One session: its sales and the cash closing. */
    public function session(Request $request): View
    {
        $session = PosSession::with('register.cashAccount', 'user')->findOrFail($request->route('id'));

        return view('pos.session', [
            'session' => $session,
            'sales' => $session->sales()->with('customer')->get(),
            'expected' => $session->isOpen() ? Pos::expectedCash($session) : $session->expected_cash,
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        $data = $request->validate(['counted_cash' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:255']]);
        $session = Pos::close(PosSession::findOrFail($request->route('id')), $data['counted_cash'], $data['notes'] ?? null);
        AuditLog::record('closed register', $session->register?->name, ['expected' => $session->expected_cash, 'counted' => $session->counted_cash, 'difference' => $session->difference]);

        return redirect()->route('pos.session', ['id' => $session->id])->with('success', __('Register closed.'));
    }
}
