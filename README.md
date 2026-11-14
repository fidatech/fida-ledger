# Fida Ledger

Multi-tenant double-entry accounting and business management, by Fida Technologies.
Laravel 13, Blade, MySQL 8. The interface is built on the DueBook / fida-admin template
(same layout, components, light/dark, phone tab bar). No Node is needed: assets are built by
`php tools/build-assets.php`.

> **Status: stage 1 of the build. This is not the finished product.**
> `docs/PROGRESS.md` lists exactly what exists, what does not, and what has not been run yet.
> The code in this stage was written in an environment where Composer packages and MySQL could
> not be installed, so **the application and its tests have not been executed**. Every PHP file
> passes `php -l`; nothing more is claimed. Run the steps below first and fix what they show.

## Install locally

Requirements: PHP 8.3+ with `bcmath`, `pdo_mysql`, `mbstring`, `intl`; Composer; MySQL 8.

```bash
composer install
cp .env.example .env
php artisan key:generate
# create the database named in .env (DB_DATABASE=fida_ledger), then:
php artisan migrate          # also creates the Starter and Business plans and the seven modules
php artisan db:seed          # local only: administrator admin@fidaledger.test / password123
php artisan serve
```

- Company side: http://localhost:8000 (register, set up a company, send a payment)
- Platform side: http://localhost:8000/admin (approve the payment; the company then opens)

## Tests

```bash
php artisan test                                                   # SQLite in memory (quick)
DB_CONNECTION=mysql DB_DATABASE=fida_ledger_test php artisan test  # MySQL: the one that counts
```

SQLite adds DECIMAL columns as floating point, so only the MySQL run proves the amounts.

## Where things are

| Path | What |
| --- | --- |
| `app/Accounting/Ledger.php` | The only way into the general ledger: post and reverse |
| `app/Accounting/{Money,Transfers,Chart,Balances}.php` | Money In/Out, transfers and exchange, opening accounts, balances |
| `app/Support/{Companies,Billing,Modules}.php` | Opening a company, subscriptions, module entitlements |
| `app/Support/Dec.php` | Exact decimal arithmetic (bcmath). Money is never a float |
| `app/Http/Middleware` | `company` (tenant context), `subscribed`, `module:<key>`, `can.do:<permission>` |
| `config/ledger.php` | Chart of accounts, account kinds, Money In/Out kinds, roles |
| `config/modules.php`, `config/billing.php` | The seven Business modules and their dependencies; plans and prices |
| `docs/ARCHITECTURE.md` | Design, module dependency map and the accounting policies chosen |
| `docs/PROGRESS.md` | What is done, what is next, what is unverified |
