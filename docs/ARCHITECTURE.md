# Fida Ledger: architecture and accounting policies

## 1. Tenancy

- One Laravel application, one database. Every company row carries `company_id`.
- A user can belong to several companies (`company_user`, with a role in each).
- The **current company** is decided once per request by the `company` middleware
  (`SetCompany`): from the session (the header switcher), else the company used last, else the
  first. It is checked against the user's active memberships and is never read from a URL or a
  form. It is held in the container as `current.company`; `company()` reads it.
- Models use `BelongsToCompany`: a global scope filters by the current company and new rows are
  stamped with it. A row cannot be saved without a company. Services that run without a current
  company (console, admin) name the company explicitly and bypass the scope on purpose.
- Form input that points at another record is validated with `own('table')`
  (the id must belong to the current company).
- Files (payment receipts, attachments) are on the private disk and served only by controllers
  that first load the owning record through the company scope.
- After sign-in the user lands on the dashboard of the company used last. There is no company
  selection page. A company without a running subscription is redirected to Subscription & Billing.

## 2. Plans, billing and modules

- Two plans in the `plans` table: Starter (setup 200, annual 100) and Business (setup 400,
  annual 200), USD. No free plan, no trial. Prices are data, to be edited in the admin panel.
- The setup fee is charged once per company (`companies.setup_fee_paid`); a renewal charges the
  annual fee only.
- A company pays by sending evidence (`subscription_payments`, status `pending`). **Only an
  administrator's approval activates**: it sets the company `active` and the end date one year
  on. A renewal approved early starts when the running year ends.
- Upgrade (Starter to Business) has no automatic price: it is left to the administrators by
  design, and is not built yet.
- Modules: `config/modules.php` holds the seven Business modules and their dependencies; the
  `modules` table holds platform availability and which plans include each; `company_modules`
  holds each company's switches.

Dependency map:

```
products ──┬── purchasing
           ├── inventory ──┐
           ├── sales ──────┼── pos   (pos needs products + sales + inventory)
fixed_assets               │
payroll
```

- **The plan decides the modules.** A company does not pick them: opening a Business company
  switches on all seven, a Starter company has none, and the registration and Settings screens
  only show what the version includes. `Modules::set()` remains for the platform side (a plan change).
- Switching a module on switches on what it needs. A module that an active module needs
  cannot be switched off. Starter is refused any Business module. Switching off deletes nothing.
- Enforcement is on the server: routes of a module use `module:<key>`; the menu only mirrors it.

## 3. The ledger

- `journal_entries` + `journal_lines`. `App\Accounting\Ledger::post()` is the only writer.
- An entry is accepted only if: the date is after `companies.locked_until`; each line is a
  positive debit or credit on a postable (non-group, non-archived) account of the company; a
  cash or bank account is posted in its own currency; total debits equal total credits in the
  base currency exactly.
- Each line stores the amount in its own currency, the rate, and the base-currency value.
- Posted entries and lines are immutable (model guards throw). A correction is
  `Ledger::reverse()`: a mirror entry linked both ways.
- Idempotency: documents carry a one-time key from the form (`unique(company_id, idempotency_key)`),
  so a double submit returns the first document. Numbers come from `sequences` under a row lock.
- Amounts are `DECIMAL(20,4)`, rates `DECIMAL(20,8)`; arithmetic is `App\Support\Dec` (bcmath),
  rounding half away from zero. Display shows 2 decimals; storage keeps 4.
- There are no cached balances. `App\Accounting\Balances` sums journal lines in SQL.

## 4. Accounts

- Customers, suppliers, sarafs, personal accounts, loans, cash boxes and banks are all accounts
  of the chart, each under its group (`config/ledger.php` 'kinds'). One statement and one
  balance rule serve all of them.
- Cash and bank accounts hold one currency. Party accounts (customer, supplier, saraf ...) can
  be posted in any currency; their balance is kept per currency and never mixed.
- Sarafs and personal accounts are classed as assets and may swing to a credit balance; the
  statement shows the sign.
- Accounts are archived, never deleted. An account with a balance cannot be archived.
- Opening balances are optional and post against "Opening balances" (equity, 3900).

## 5. Money In and Money Out

- Money In: debit cash/bank, credit the other account. Money Out: the reverse.
- The user picks what the money is (customer receipt, expense, loan received ...). That pick
  limits the accounts on the other side, and the class of that account gives the accounting:
  a customer receipt lowers a receivable, a loan received raises a liability, and only income
  and expense accounts touch profit. So money received is not income unless it is income.
- Cash to cash is not a Money In/Out; it is a transfer.
- Cancelling reverses the entry and keeps the movement, marked `void`.

## 6. Currency exchange (policy chosen)

- Foreign cash is carried at **weighted average cost** in the base currency.
- An exchange takes the currency given out of the books at its carrying value, brings the
  currency received in at its base value, and posts the difference to "Exchange gain or loss"
  (4950) as a **realized** difference.
  - Buying foreign currency with base currency: it comes in at what was paid; no gain or loss.
  - Selling foreign currency for base currency: gain or loss = base received − carrying value.
  - Foreign to foreign: the currency received is valued at its saved rate (or a rate typed on the form).
- When an account does not hold as much as is given (it goes negative), the saved rate of the
  day is used instead of the average.
- **Not built yet:** period-end revaluation of monetary balances (unrealized differences), and
  realized differences when a foreign receivable or payable is settled at a different rate.

## 7. Reports

- Trial balance, profit & loss and balance sheet are sums of journal lines in the base
  currency, so they agree with the ledger by construction. Until a year-end closing exists,
  the balance sheet shows undistributed profit as its own equity line.

## 8. Invoicing

- A draft posts nothing and can be changed. Issuing posts once (idempotent): debit the
  customer, credit the income account of each line and "Taxes payable". A credit note posts
  the same entry with the sides swapped.
- A payment is a Money In from the customer. `Invoices::allocate()` only links it to the
  invoice (`invoice_payments`) and raises `invoices.paid`; it posts nothing, so income is
  counted once. Cancelling the Money In removes the link and the invoice is due again.
- An invoice is paid in its own currency. An invoice with payments cannot be cancelled.
- In a foreign currency the customer's line carries the sum of the other lines' base values,
  so rounding can never unbalance the entry.
- Discounts are per line (an amount). Tax is a percentage per line.

## 9. Policies adopted for the Business modules

The owner asked for the system to be completed without answering these, so the proposals
stand and are to be implemented as written. Changing one later means changing the module, not
the ledger.

1. Inventory costing: **moving weighted average** per product and warehouse.
2. Purchases: **accrue at goods receipt** (debit Inventory, credit "Goods received not
   invoiced"); the supplier bill clears that account against the supplier. Stock is raised
   once, by the receipt.
3. Negative stock: **forbidden**. A delivery or POS sale of more than is on hand is refused.
4. Payroll taxes and deductions: configurable tables only; no national rules are built in.
5. Upgrade from Starter to Business: done by an administrator; the price is not automated.
