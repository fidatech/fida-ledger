<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => \App\Casts\Day::class, 'valid_until' => \App\Casts\Day::class];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'customer_account_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SalesEvent::class)->orderBy('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isQuotation(): bool
    {
        return $this->type === 'quotation';
    }

    public function code(): string
    {
        return ($this->isQuotation() ? 'QT-' : 'SO-').str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }
}
