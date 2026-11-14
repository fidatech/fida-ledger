# Fida Ledger: progress

Last updated: 2026-11-15 (stage 6). Branch: `development`.

## Read this first

Stages 1 to 3 were written in a cloud workspace where Composer packages and MySQL could not be
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

## Built in stage 3 (unverified in the same way)

All six remaining Business modules now have tables, services, screens, permissions, posting
and a workflow test (`tests/Feature/BusinessModulesTest`, not run):

- **Inventory**: warehouses, stock overview with average cost and value, low-stock filter,
  movements, transfers, adjustments, physical counts, opening stock, CSV export, and a
  reconciliation of stock value against the Inventory account. Moving weighted average; stock
  cannot go negative.
- **Purchasing**: purchase orders (draft → approved → completed), approval as its own
  permission, partial goods receipts, partial supplier bills, purchase returns, "Pay supplier".
  Receipt accrues to "Goods received not invoiced"; the bill clears it.
- **Sales Management**: quotations, sales orders, partial deliveries (cost of goods sold),
  invoicing of what was delivered through the shared invoicing service, customer returns with
  automatic credit note.
- **Point of Sale**: registers with their own cash drawer, cashier sessions, a selling screen
  with search, barcode entry, cart, discounts, split payments and change, receipts, full
  refunds, cash closing with shortage or surplus posted. A POS sale is an invoice + stock issue
  + Money In made by the shared services.
- **Fixed Assets**: categories, register, acquisition, straight-line monthly depreciation
  (repeat-safe), schedule, maintenance and transfer log, disposal with gain or loss.
- **Payroll**: departments, employees with salary structure, salary advances, monthly payroll
  (one per month), editable draft, approval (accrual), payment, payslips.
- Dashboard figures for the modules that are switched on.

## Built in stage 4 (written without being run, like the rest)

- Modules are decided by the plan (no selection by the company); cash payments need no receipt.
- Manual journal entries with reversal; Inventory and the purchase accrual are closed to them.
- Year-end closing to Retained earnings with period lock; Profit & Loss ignores closing entries.
- Report book with CSV and print: cash flow, receivables aging, currency balances, tax, audit,
  product sales, purchases by supplier, warehouse summary, POS daily sales, depreciation,
  employee payroll. The Reports page lists every report, grouped, by module and permission.
- Tests written: `tests/Feature/ReportsTest`.

## Built in stage 5 (written without being run, like the rest)

Banking & Planning, for the Business version (hidden and refused for Starter):
- Bank reconciliation per cash or bank account: CSV statement import (repeat-safe), automatic
  matching by amount and date, manual match and unmatch, lists of what is on the statement but
  not in the books and the reverse. Matching never posts or changes an entry.
- Budgets: a yearly amount per income and expense account spread over twelve months, and a
  budget-against-actual comparison for any range of months, with CSV.
- Recurring Money In and Money Out: daily, weekly, monthly or yearly, an optional end date, a
  rule for missed dates (post all, or only the latest), an hourly job (`ledger:recurring`) and
  a "post what is due now" button. Each due date is posted once.
- Date-only columns now use the `Day` cast, so dates compare the same on MySQL and on SQLite.
- Tests written: `tests/Feature/BankingTest`.

## Built in stage 6 (written without being run, except the one-time-code maths)

- Two-factor sign-in (authenticator app, RFC 6238) with one-time recovery codes; the code
  generator was checked against the published test vectors.
- My Profile & Security: name, password change, two-factor setup and switch-off.
- Email verification, off by default (`EMAIL_VERIFICATION=true` switches it on).
- Platform side: company page (change version, correct the subscription date, with a required
  reason), users (block / restore), administrators and their roles, audit log, revenue by month
  and by version, module entitlements applied to all companies, announcements on the company
  dashboard, system health.
- Tests written: `tests/Feature/SecurityAdminTest`.

## Not built yet

Inside the Business modules:
- Products: variants, price lists, barcode label printing, product images.
- Purchasing: requisitions, landed costs, bills at a price different from the order, tax on purchases.
- Inventory: bin locations, batch/lot tracking, a multi-line count sheet (counts are per product).
- Sales: sales reports beyond the lists; delivery note print layout uses the order page.
- POS: partial refunds, held carts, receipt printer formatting, offline mode, foreign-currency sales.
- Fixed Assets: depreciation methods other than straight line; asset reports.
- Payroll: positions and structures as separate records, tax tables, per-employee payment, payroll reports.

Starter scope still missing:
- A local mail viewer (emails go to the mail log locally); a QR code for two-factor setup (the key is typed in).
- Invoice templates (one layout exists), invoice-level discount, WhatsApp sharing.
- Money In/Out categories.
- Monetary revaluation; FX difference on settling foreign receivables/payables.
- Payables aging by bill (supplier payments are not matched to single bills).
- PDF files made by the server (reports print through the browser, which can save a PDF).
- Settings: custom roles, numbering sequences, branding, language, security, API access.
- Dari and Pashto translations (the layout is RTL-ready; strings are English only).
- Scheduled reminders; bank statement formats other than CSV.
- WhatsApp / RelayWA integration. The provider's API was not consulted yet.
- Sanctum API.

Platform side still missing: promotions, a platform settings page, a background-jobs viewer,
support access to a company's books (administrators cannot see them at all, by design).

Deliverables not produced: user and super-admin documentation, deployment instructions,
API documentation, source ZIP.

## Suggested order for the next stages

1. Run everything on MySQL (`docs/INSTALL-MAC.md`), fix what fails, then a browser pass on
   desktop and phone. Nothing below should start before this.
2. Dari and Pashto translations; RelayWA; Sanctum API; documentation.
