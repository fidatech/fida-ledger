<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }
}
