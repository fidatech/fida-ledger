<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An account of the chart. Customers, suppliers, sarafs, cash boxes and banks are all accounts
 * (each under its group), so one statement and one balance rule serve them all.
 */
class Account extends Model
{
    use BelongsToCompany;

    public const CASH_KINDS = ['cash', 'bank'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_group' => 'boolean', 'is_archived' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** Accounts that can be posted to. */
    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_group', false)->where('is_archived', false);
    }

    public function scopeCashOrBank(Builder $query): Builder
    {
        return $query->whereIn('kind', self::CASH_KINDS);
    }

    /** A special account of a company by its system key ('opening_balances', 'exchange_difference' ...). */
    public static function system(int $companyId, string $key): self
    {
        return self::withoutGlobalScope('company')->where('company_id', $companyId)->where('system_key', $key)->firstOrFail();
    }

    public function isCash(): bool
    {
        return in_array($this->kind, self::CASH_KINDS, true);
    }

    /** 'debit' or 'credit': the side on which this account grows. */
    public function normalSide(): string
    {
        return config('ledger.classes.'.$this->class, 'debit');
    }

    public function kindLabel(): string
    {
        return __(config('ledger.kinds.'.$this->kind.'.label', ucfirst(str_replace('_', ' ', (string) ($this->kind ?: $this->class)))));
    }

    public function label(): string
    {
        return $this->name.($this->currency ? ' ('.$this->currency.')' : '');
    }
}
