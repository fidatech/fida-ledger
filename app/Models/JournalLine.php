<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** One debit or credit of a journal entry. Written once by the Ledger, never changed or deleted. */
class JournalLine extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('A posted journal line cannot be changed.');
        });

        static::deleting(function () {
            throw new LogicException('A posted journal line cannot be deleted.');
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
