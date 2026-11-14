<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTransaction extends Model
{
    use BelongsToCompany;

    public const FREQUENCIES = ['daily' => 'Every day', 'weekly' => 'Every week', 'monthly' => 'Every month', 'yearly' => 'Every year'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['next_run' => \App\Casts\Day::class, 'end_date' => \App\Casts\Day::class, 'last_run' => \App\Casts\Day::class, 'is_active' => 'boolean'];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }
}
