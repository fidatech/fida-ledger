<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetEvent extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => \App\Casts\Day::class];
    }
}
