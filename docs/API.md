# Fida Ledger API (version 1)

For other programs: a mobile app, a desktop app, a script. Everything the API does goes through
the same rules as the screens: the company's subscription, the user's role, the module switches
and the company isolation.

> Status: written and covered by tests in `tests/Feature/LanguageWhatsappApiTest.php`, but those
> tests have not been run yet. Run `php artisan test` before building an app on it.

## Sign in

1. In the browser open **Settings > API Access** (owner or administrator).
2. Make a key: a name and "Read only" or "Read and write". The key is shown once.
3. Send it with every request:

```
Authorization: Bearer fl_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

A key works in **one company**, as **the person who made it**, with that person's role. It stops
working when it is revoked, when that person is switched off or removed from the company, or
when the subscription ends. Only the SHA-256 of a key is stored.

This is the system's own key scheme. Laravel Sanctum is not installed: it could not be installed
or tested where this was written. The routes do not depend on the scheme, so Sanctum can replace
`App\Http\Middleware\ApiAuth` later without changing the addresses below.

## Rules

- Base address: `https://your-domain/api/v1`
- Limit: 120 requests a minute.
- Amounts are strings with four decimals (`"1250.0000"`). Do not turn them into floats.
- Dates are `YYYY-MM-DD` in the company's time zone.
- Lists are paged: `?page=2&per_page=50` (at most 100). The answer has `data` and `meta`.

| Status | Meaning |
|---|---|
| 401 | Key missing, wrong or revoked |
| 402 | The company's subscription is not active |
| 403 | The role does not allow it, the key is read-only, or the module is off |
| 404 | No such record in this company |
| 422 | The data is not valid; `message` (and `errors`) say why |
| 429 | Too many requests |

## Addresses

| Method | Address | Permission | What |
|---|---|---|---|
| GET | `/me` | — | The user, role, company, version, active modules |
| GET | `/summary` | money.view | Cash and bank per currency, what customers owe, what is owed to suppliers |
| GET | `/accounts` | accounts.view | Filters: `kind`, `q`, `archived=1` |
| GET | `/accounts/{id}` | accounts.view | One account with its balance per currency |
| GET | `/money` | money.view | Filters: `direction=in|out`, `from`, `until`, `account` |
| POST | `/money` | money.create, write key | Record Money In or Money Out |
| GET | `/invoices` | invoices.view | Filters: `type`, `status`, `customer`, `from`, `until` |
| GET | `/invoices/{id}` | invoices.view | One invoice with its lines |
| GET | `/products` | products.view, Products module | Filters: `type`, `q`, `inactive=1` |

### Record money

```
POST /api/v1/money
{
  "direction": "in",
  "kind": "customer",
  "date": "2026-11-15",
  "cash_account_id": 12,
  "counter_account_id": 40,
  "amount": "1500",
  "description": "Payment for INV-000004",
  "idempotency_key": "b1f3c9a2-phone-0001"
}
```

- `kind` is one of the kinds under `money.in` / `money.out` in `config/ledger.php`.
- `rate` is needed only when the cash account's currency is not the base currency and no rate
  is saved for that day.
- `idempotency_key` is required. Sending the same key again returns the first record (status
  200 instead of 201) and posts nothing twice. Make a new key for every new payment.
- The posting to the ledger is the same as on the Money In / Money Out screen.

Not in version 1: creating invoices, stock, POS, payroll, reports. Linking a payment to an
invoice is done on the screen.
