# Putting Fida Ledger on a server

> Nothing here has been carried out by the author of the code: the application was written
> without a place to run it. Treat the first deployment as a test, on a staging address first.

## What the server needs

- PHP 8.4 with: bcmath (required: all money maths), mbstring, openssl, pdo_mysql, fileinfo, ctype, json, curl
- MySQL 8 (utf8mb4)
- Composer 2, a web server (Nginx or Apache) pointing at `public/`
- HTTPS. The API keys and the WhatsApp session key travel in requests.

No Node is needed: CSS, JS and icons are built by `php tools/build-assets.php` and are committed.

## First install

```bash
git clone https://github.com/fidatech/fida-ledger.git && cd fida-ledger
git checkout development            # until a release is merged to main
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
```

Edit `.env`:

```
APP_NAME="Fida Ledger"      # keep the quotes: a value with a space must be quoted
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ledger.example.com
DB_DATABASE=...  DB_USERNAME=...  DB_PASSWORD=...
MAIL_MAILER=smtp  (and the MAIL_ values of your mail service)
EMAIL_VERIFICATION=true
SESSION_SECURE_COOKIE=true
```

Then:

```bash
php artisan migrate --force
php artisan app:create-admin        # the first system administrator (/admin)
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`APP_KEY` encrypts the two-factor secrets and the WhatsApp session keys. Back it up. If it is
lost or changed, those must be entered again.

## Every minute: the scheduler

Recurring Money In / Money Out are posted by the scheduler. Add to the server's crontab:

```
* * * * * cd /path/to/fida-ledger && php artisan schedule:run >> /dev/null 2>&1
```

## Folders that must be writable and backed up

- `storage/` (writable). `storage/app/private` holds payment receipts and attachments.
- The database. Back up both, daily, and test a restore.

## Updating

```bash
php artisan down
git pull && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## Check after installing

- `/up` answers 200.
- `/admin` > System Health is green (it checks bcmath, storage, debug mode, the scheduler).
- Register a company, pay by cash, approve it in `/admin`, record a Money In, open the Trial Balance.
- `php artisan test` on a staging copy (it uses its own in-memory database).
