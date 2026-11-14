<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function label(): string
    {
        return date('F Y', strtotime($this->period.'-01'));
    }
}
