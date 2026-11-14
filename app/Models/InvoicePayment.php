<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part of a Money In that settles an invoice. The money itself was posted once, by the Money In. */
class InvoicePayment extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(MoneyMovement::class, 'money_movement_id');
    }
}
