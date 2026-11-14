# Fida Ledger: progress

Last updated: 2026-11-15 (stage 2). Branch: `development`.

## Read this first

Stages 1 and 2 were written in a cloud workspace where Composer packages and MySQL could not be
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

## Built in stage 2 (unverified in the same way)

- Invoicing: drafts, issue, credit notes, cancel by reversal, printable document, list with
  status filters, "Receive payment" through Money In with allocation (part and full payment).
- Products & Services module (the first Business module): products, services, categories,
  units, SKU, barcode, prices, tax rate, stock flag, reorder level; invoice lines can pick from it.
  The module's screens exist only while the module is switched on.
- Settings: company profile and accounting period lock, users & roles (add, change role,
  switch access off, seat limit), currencies (add, switch on or off).
- Password recovery by email link.
- Excel-compatible CSV export of Money In/Out history, account statements and the trial balance.
- Platform side: companies list with suspend / restore, plan and price editing.
- Tests written (not run): `tests/Feature/{Invoice,Settings}Test`.

## Not built yet

Starter scope still missing:
- Email verification, optional 2FA, a local mail viewer (password links go to the mail log locally).
- Invoice templates (one layout exists), invoice-level discount, refunds linked to credit notes,
  WhatsApp sharing.
- Money In/Out categories; manual journal entries.
- Monetary revaluation; FX difference on settling foreign receivables/payables; year-end closing.
- Remaining reports: cash flow, cashbook/bankbook, aging, currency balances, exchange gain/loss,
  tax, audit log viewer. PDF export (pages print through the browser; CSV covers Excel).
- Settings still missing: custom roles, numbering sequences, branding, language, security, API access.
- Dari and Pashto translations (the layout is RTL-ready; strings are English only).
- WhatsApp / RelayWA integration. The provider's API was not consulted yet.
- Sanctum API.

Business modules: **one of seven is built** (Products & Services, without variants, price
lists and barcode printing). Not built: Purchasing, Inventory, Sales Management, Point of
Sale, Fixed Assets, Payroll. Their switches exist in Settings; their screens do not. Bank
reconciliation, budgets and recurring transactions are not built either.

Platform side still missing: users, module entitlement editing, promotions, announcements,
settings, audit log viewer, jobs, health, revenue reports, administrators management.

Deliverables not produced: user and super-admin documentation, deployment instructions,
API documentation, source ZIP.

## Suggested order for the next stages

1. Run and fix stages 1 and 2 on MySQL. Browser pass on desktop and phone.
2. Inventory (warehouses, movements, weighted average) → Purchasing → Sales → POS, each
   posting through `Ledger::post()` with the policies in `docs/ARCHITECTURE.md` section 9.
3. Fixed Assets and Payroll.
4. The rest of Starter (reports, exports, verification, translations) and of the admin panel.
5. Banking (reconciliation, imports), budgets, recurring transactions.
6. RelayWA, Sanctum API, documentation, acceptance run.
