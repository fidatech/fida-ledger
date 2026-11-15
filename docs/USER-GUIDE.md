# User guide

## Starting

1. **Register** with your name, email and password, then set up your company: name, type, base
   currency, and the version (Starter or Business). The version decides the modules.
2. **Pay.** Subscription & Billing shows the amount (setup fee once, plus one year). Choose how
   you paid and upload the receipt (not needed for cash). The company opens when the Fida
   Ledger team approves the payment.
3. After that you always land on the **Dashboard**. Switch company with the building icon in
   the header; switch language (English, دری, پښتو) with the globe.

## The idea

Everything is an **account**: customers, suppliers, sarafs, cash boxes, banks, loans. Every
screen posts to one double-entry ledger, so the reports always agree with each other.

## Every day (both versions)

- **Money In / Money Out.** Choose what the money is (from a customer, to a supplier, expense,
  loan …), the cash or bank account and the amount. In another currency, enter the rate. A wrong
  record is cancelled, not deleted: a reversing entry is posted and both stay visible.
- **Accounts.** Open an account to see its balance per currency and its statement; print it or
  export it to Excel (CSV).
- **Transfers & Exchange.** Move money between your own cash and bank accounts, or exchange
  currency. Exchange gain or loss is worked out for you.
- **Invoicing.** Draft, then Issue (it enters the books and can no longer be edited). Receive
  payment from the invoice. A credit note takes an invoice back, fully or partly.
- **General Journal.** For accountants: manual entries and reversing.
- **Reports.** Trial Balance, Profit & Loss, Balance Sheet, cashbook, cash flow, aging, tax,
  and more. Print from the browser to get a PDF.

## Business version

Products, Purchasing (order, receive, bill, return), Inventory (warehouses, average
cost, transfers, adjustments, counts), Sales (quotation, order, deliver, invoice), Point of
Sale (registers, cashier sessions, cash count), Fixed Assets (monthly straight-line
depreciation, disposal), Payroll (employees, advances, monthly run, payslips), and Banking &
Planning (bank reconciliation from CSV, budgets, recurring Money In / Out).

## Settings

- **Company Profile:** details, time zone, period lock, year-end closing.
- **Users & Roles:** add people and give each a role (accountant, cashier, salesperson,
  storekeeper, payroll, viewer).
- **Currencies** and **Exchange Rates.**
- **WhatsApp:** see below.
- **API Access:** keys for other programs (see `docs/API.md`).
- **My Profile & Security** (your name at the top right): password and two-factor sign-in.

## WhatsApp

Messages go through RelayWA, which links your own WhatsApp number by QR code (like WhatsApp
Web; it is not Meta's official business API).

1. Make an account and a session at relaywa.com and link your number there.
2. In Settings > WhatsApp paste the **session key**, enter your **country code** (93 for
   Afghanistan), switch sending on, Save.
3. Press **Check connection**, then send yourself a test message.
4. On an issued invoice, a Money In receipt or an account page, press **WhatsApp**. The message
   is written for you from your texts; read it, correct it, Send.

Good to know: only text is sent (print the document to PDF and attach it in WhatsApp if you
need the file). The log says "Accepted by RelayWA" when RelayWA took the message; the system
does not know whether it was delivered or read. Save phone numbers on accounts with the
country code.

## Languages

The whole interface is in English, Dari and Pashto. Dates are Gregorian and digits are Latin
in every language. A few built-in form messages ("The name field is required") are English.
