# Install Fida Ledger on a MacBook

Repository: https://github.com/fidatech/fida-ledger (branch `development`)

## 1. What the Mac needs

```bash
brew install php composer mysql      # PHP 8.3 or newer; Homebrew's PHP already has bcmath
brew services start mysql
php -m | grep -i -E "bcmath|pdo_mysql|mbstring|intl"   # all four must be listed
```

## 2. Get the code

```bash
cd ~/Sites            # or wherever you keep projects
git clone -b development https://github.com/fidatech/fida-ledger.git
cd fida-ledger
```

## 3. Set it up

```bash
composer install
cp .env.example .env
php artisan key:generate
mysql -u root -e "CREATE DATABASE fida_ledger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE fida_ledger_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Open `.env` and check `DB_DATABASE=fida_ledger`, `DB_USERNAME=root` and `DB_PASSWORD=` (your
MySQL root password, empty on a fresh Homebrew install).

```bash
php artisan migrate      # tables, the Starter and Business plans, the seven modules
php artisan db:seed      # the local administrator
php artisan serve
```

## 4. Run the tests before anything else

This code has never been executed. The tests show what needs fixing first.

```bash
php artisan test
DB_CONNECTION=mysql DB_DATABASE=fida_ledger_test DB_USERNAME=root DB_PASSWORD= php artisan test
```

## 5. Try it

| What | Where | Sign in |
| --- | --- | --- |
| Company side | http://localhost:8000 | register a new account |
| Platform side | http://localhost:8000/admin | `admin@fidaledger.test` / `password123` |

1. Register, choose Starter or Business, enter the company, upload any image as the receipt.
2. In another browser (or a private window) open `/admin` → Payment approvals → Approve.
3. Back on the company side: the dashboard opens. Record a Money In, add a customer, create
   and issue an invoice, receive its payment, open the trial balance.

Password-reset emails are written to `storage/logs/laravel.log` locally (`MAIL_MAILER=log`).

After changing a view or a style: `php tools/build-assets.php` rebuilds the CSS, JS and icons.
