<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A customer invoice or a credit note. A draft can be changed; once issued it is in the ledger and can only be cancelled. */
class Invoice extends Model
{
    use BelongsToCompany;

    public const INVOICE = 'invoice';
    public const CREDIT_NOTE = 'credit_note';
    public const DRAFT = 'draft';
    public const ISSUED = 'issued';
    public const VOID = 'void';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => \App\Casts\Day::class, 'due_date' => \App\Casts\Day::class];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'customer_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function code(): string
    {
        return ($this->type === self::CREDIT_NOTE ? 'CN-' : 'INV-').str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }

    public function isCreditNote(): bool
    {
        return $this->type === self::CREDIT_NOTE;
    }

    /** What the customer still has to pay. A credit note is never "due". */
    public function due(): string
    {
        return $this->status === self::ISSUED && ! $this->isCreditNote() ? Dec::sub($this->total, $this->paid) : '0.0000';
    }

    /** draft, void, credit, paid, partial, overdue or unpaid: what the list shows. */
    public function state(): string
    {
        if ($this->status !== self::ISSUED) {
            return $this->status;
        }
        if ($this->isCreditNote()) {
            return 'credit';
        }
        if (! Dec::isPositive($this->due())) {
            return 'paid';
        }
        if (Dec::isPositive($this->paid)) {
            return 'partial';
        }

        return $this->due_date !== null && $this->due_date->toDateString() < today_iso() ? 'overdue' : 'unpaid';
    }
}
