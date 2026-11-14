<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A posted journal entry. It is never edited or deleted: a mistake is corrected by a
 * reversing entry (App\Accounting\Ledger::reverse). Only the link to its reversal may be set.
 */
class JournalEntry extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => \App\Casts\Day::class];
    }

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            if (array_diff(array_keys($entry->getDirty()), ['reversed_by_id', 'updated_at'])) {
                throw new LogicException('A posted journal entry cannot be changed. Reverse it instead.');
            }
        });

        static::deleting(function () {
            throw new LogicException('A posted journal entry cannot be deleted. Reverse it instead.');
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_id !== null;
    }
}
