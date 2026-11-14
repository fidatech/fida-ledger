# Fida Ledger: progress

Last updated: 2026-11-15 (stage 1). Branch: `development`.

## Read this first

Stage 1 was written in a cloud workspace where Composer packages and MySQL could not be
installed. **Nothing here has been run**: no `composer install`, no migration, no test, no
browser. What was checked: every PHP file passes `php -l`; Blade directives are balanced; every
icon used exists in the sprite; the asset build (`php tools/build-assets.php`) ran and its
output is committed. The first job on a machine with PHP 8.3+/bcmath, Composer and MySQL is:

1. `composer install`, `php artisan migrate`, `php artisan test` (SQLite), then the MySQL run.
2. Fix what fails. Expect some failures: this is unexecuted code.
3. Click through: register → set up company → pay → `/admin` approve → dashboard → Money In.

## Built in stage 1 (unverified until the steps above pass)

- Project skeleton from the DueBook template: layout, sidebar, header, components, CSS/JS
  build, phone tab bar, light/dark. DueBook's business logic was not copied. DueBook itself
  was only read.
- Registration (name, email, password), sign-in with rate limiting, sign-out.
- Company setup: Starter/Business with real prices, the seven modules ticked by default for
  Business, company details. The company starts pending.
- Subscription & Billing: quote (setup fee once, annual fee), payment evidence upload to the
  private disk, pending / active / ended states, renewal window, payment history.
- Platform side `/admin`: separate sign-in, dashboard counts, payment approval and rejection
  with fixed reasons, audit log entries.
- Tenancy: company context middleware, scoped models, header company switcher, add a company.
- Roles and permissions enforced in routes; module entitlement engine with dependencies.
- Ledger: chart of accounts, posting, reversal, period lock, immutability, idempotency, sequences.
- Accounts: all kinds, optional opening balance, detail page with per-currency balances and
  statement, archive/restore, chart of accounts tree.
- Money In / Money Out: record (with confirmation), history with filters and totals, receipt,
  attachment, cancel by reversal.
- Transfers and currency exchange with realized gain or loss; exchange rates.
- General journal (read-only), trial balance, profit & loss, balance sheet.
- Dashboard: cash per currency, base total, receivables, payables, Money In/Out, profit, recent.
- Tests written (not run): `tests/Unit/DecTest`, `tests/Feature/{Registration,Ledger,Isolation}Test`.

## Not built yet

Starter scope still missing:
- Email verification, password recovery, optional 2FA, local mail viewer.
- Invoicing (invoices, receipts, credit notes, templates), taxes.
- Money In/Out categories; manual journal entries; loans & debts screens beyond the accounts.
- Monetary revaluation; FX difference on settling foreign receivables/payables; period closing UI.
- Remaining reports: cash flow, cashbook/bankbook, aging, currency balances, exchange gain/loss
  report, tax, audit. PDF / Excel / CSV export (pages print through the browser for now).
- Company settings: profile, users & roles (inviting users, custom roles), currencies, financial
  year and period locks UI, numbering, language, security, API access.
- Dari and Pashto translations (the layout is RTL-ready; strings are English only).
- WhatsApp / RelayWA integration. The provider's API was not consulted yet.
- Sanctum API.

Business modules: **none of the seven is built** (Products & Services, Purchasing, Inventory,
Sales, Point of Sale, Fixed Assets, Payroll). The entitlement switches exist; the modules do
not. Bank reconciliation, budgets and recurring transactions are not built either.

Platform side still missing: companies list with suspend/restore, users, plan and price
editing, module entitlement editing, seat limits, promotions, announcements, settings,
audit log viewer, jobs, health, revenue reports, administrators management.

Deliverables not produced: user and super-admin documentation, deployment instructions,
API documentation, source ZIP.

## Decisions needed from the owner

See `docs/ARCHITECTURE.md` section 8 (inventory costing, purchase accrual, negative stock,
payroll rules, upgrade price).

## Suggested order for the next stages

1. Run and fix stage 1 on MySQL. Browser pass on desktop and phone.
2. Finish Starter: invoicing, settings (users & roles, currencies, period locks), remaining
   reports and exports, email verification and password recovery, fa/ps translations.
3. Admin panel: companies, plans and prices, entitlements, audit viewer.
4. Business modules in dependency order: Products → Inventory → Purchasing → Sales → POS,
   then Fixed Assets and Payroll, each posting through `Ledger::post()`.
5. Banking (reconciliation, imports), budgets, recurring transactions.
6. RelayWA, Sanctum API, documentation, acceptance run.
