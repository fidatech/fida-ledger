<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** One unit of a currency in the company's base currency, on a day. */
class ExchangeRate extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /** The latest rate on or before a day, or null when none was ever entered. */
    public static function on(Company $company, string $currency, string $date): ?string
    {
        if ($currency === $company->base_currency) {
            return '1';
        }

        return self::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('currency', $currency)
            ->where('date', '<=', $date)
            ->orderByDesc('date')
            ->value('rate');
    }
}
