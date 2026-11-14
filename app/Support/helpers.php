<?php

use App\Models\Company;
use App\Support\Dec;
use App\Support\Modules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

if (! function_exists('asset_v')) {
    /**
     * URL of a file in public/ with its modification time appended, so browsers can
     * cache it for a year and still pick up a new version the moment it changes.
     */
    function asset_v(string $path): string
    {
        static $urls = [];

        return $urls[$path] ??= asset($path).'?v='.(is_file(public_path($path)) ? filemtime(public_path($path)) : '0');
    }
}

if (! function_exists('text_dir')) {
    /** Text direction of the current language. */
    function text_dir(): string
    {
        return in_array(app()->getLocale(), ['fa', 'ps', 'ar'], true) ? 'rtl' : 'ltr';
    }
}

if (! function_exists('company')) {
    /**
     * The company the signed-in user is working in, or null (admin panel, console, guest).
     * It is set once per request by the SetCompany middleware after checking the membership.
     */
    function company(): ?Company
    {
        return app()->bound('current.company') ? app('current.company') : null;
    }
}

if (! function_exists('company_id')) {
    function company_id(): ?int
    {
        return company()?->id;
    }
}

if (! function_exists('can_do')) {
    /** May the signed-in user do this in the current company? (config/ledger.php 'roles') */
    function can_do(string $permission): bool
    {
        $company = company();
        $user = auth('web')->user();

        return $company !== null && $user !== null && $user->canDo($company, $permission);
    }
}

if (! function_exists('module_on')) {
    /** Is this Business module active for the current company? */
    function module_on(string $module): bool
    {
        $company = company();

        return $company !== null && Modules::active($company, $module);
    }
}

if (! function_exists('own')) {
    /** Validation rule: the chosen record must belong to the current company. */
    function own(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('company_id', company_id());
    }
}

if (! function_exists('base_currency')) {
    function base_currency(): string
    {
        return company()?->base_currency ?? 'USD';
    }
}

if (! function_exists('currencies')) {
    /** The active currencies of the company: ['AFN' => 'AFN', ...], its base currency first. */
    function currencies(): array
    {
        $company = company();
        if (! $company) {
            return [];
        }
        $codes = $company->currencies()->where('is_active', true)->orderBy('code')->pluck('code')->all();
        $codes = array_values(array_unique(array_merge([$company->base_currency], $codes)));

        return array_combine($codes, $codes);
    }
}

if (! function_exists('today_iso')) {
    /** Today in the time zone of the company. */
    function today_iso(): string
    {
        return now(company()?->timezone ?: config('app.timezone'))->toDateString();
    }
}

if (! function_exists('fmt_date')) {
    /** A stored date as people read it: 14 Nov 2026. */
    function fmt_date(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        return ($date instanceof DateTimeInterface ? $date : new DateTimeImmutable(substr((string) $date, 0, 10)))->format('j M Y');
    }
}

if (! function_exists('input_date')) {
    /** A stored date as the value of a date box. */
    function input_date(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
    }
}

if (! function_exists('money')) {
    /** "1234.5" becomes "1,234.50"; with a currency code "1,234.50 USD". No float is involved. */
    function money(mixed $amount, ?string $currency = null): string
    {
        return Dec::format($amount ?? '0', 2).($currency ? ' '.$currency : '');
    }
}

if (! function_exists('sums')) {
    /** Totals by currency as one line: "1,200.00 USD · 45,000.00 AFN". An empty list gives "0". */
    function sums(iterable $totals): string
    {
        $parts = [];
        foreach ($totals as $currency => $amount) {
            if (! Dec::isZero($amount ?? '0')) {
                $parts[] = money($amount, (string) $currency);
            }
        }

        return $parts ? implode(' · ', $parts) : '0';
    }
}

if (! function_exists('per_page')) {
    function per_page(int $usual = 25): int
    {
        return $usual;
    }
}

if (! function_exists('query_date')) {
    /** A date from the address (?from=2026-11-01) as "2026-11-01", or null when it is missing or not a real date. */
    function query_date(string $key): ?string
    {
        $value = request()->query($key);
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return $value;
    }
}

if (! function_exists('quick_add')) {
    /**
     * Attributes that let a pick list add a new record in a pop-up without leaving the page:
     *   <select name="category_id" id="category" {{ quick_add(__('New category'), 'categories.create') }}>
     * The script puts a "+ New" link in the label of the list (the label must point at its id).
     */
    function quick_add(string $title, string $route, array $params = []): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString('data-quick-add="'.e(route($route, $params)).'" data-quick-title="'.e($title).'"');
    }
}

if (! function_exists('crud_routes')) {
    /** The six routes of a CrudController screen: list, add, save, edit, update, delete. */
    function crud_routes(string $uri, string $controller, string $name): void
    {
        \Illuminate\Support\Facades\Route::get($uri, [$controller, 'index'])->name($name.'.index');
        \Illuminate\Support\Facades\Route::get($uri.'/create', [$controller, 'create'])->name($name.'.create');
        \Illuminate\Support\Facades\Route::post($uri, [$controller, 'store'])->name($name.'.store');
        \Illuminate\Support\Facades\Route::get($uri.'/{id}/edit', [$controller, 'edit'])->whereNumber('id')->name($name.'.edit');
        \Illuminate\Support\Facades\Route::put($uri.'/{id}', [$controller, 'update'])->whereNumber('id')->name($name.'.update');
        \Illuminate\Support\Facades\Route::delete($uri.'/{id}', [$controller, 'destroy'])->whereNumber('id')->name($name.'.destroy');
    }
}
