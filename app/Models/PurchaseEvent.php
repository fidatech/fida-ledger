<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseEvent extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => \App\Casts\Day::class, 'due_date' => \App\Casts\Day::class, 'lines' => 'array'];
    }

    public function code(): string
    {
        return ['receipt' => 'GRN-', 'bill' => 'BILL-', 'return' => 'PRET-'][$this->type].str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }
}
