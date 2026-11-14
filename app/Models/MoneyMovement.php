<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Money In or Money Out: cash or bank money received from, or paid to, another account. */
class MoneyMovement extends Model
{
    use BelongsToCompany;

    public const IN = 'in';
    public const OUT = 'out';
    public const POSTED = 'posted';
    public const VOID = 'void';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => \App\Casts\Day::class];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kindLabel(): string
    {
        return __(config('ledger.money.'.$this->direction.'.'.$this->kind.'.label', $this->kind));
    }

    /** "MI-000012" or "MO-000007". */
    public function code(): string
    {
        return ($this->direction === self::IN ? 'MI-' : 'MO-').str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }

    public function isVoid(): bool
    {
        return $this->status === self::VOID;
    }
}
