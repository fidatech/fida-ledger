<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money moved between two cash or bank accounts of the company; an exchange when their currencies differ. */
class Transfer extends Model
{
    use BelongsToCompany;

    public const TRANSFER = 'transfer';
    public const EXCHANGE = 'exchange';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function code(): string
    {
        return ($this->type === self::EXCHANGE ? 'EX-' : 'TR-').str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }
}
