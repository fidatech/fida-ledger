<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => \App\Casts\Day::class, 'expected_date' => \App\Casts\Day::class];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'supplier_account_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PurchaseEvent::class)->orderBy('id');
    }

    public function code(): string
    {
        return 'PO-'.str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }
}
