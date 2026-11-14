<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['acquired_on' => 'date', 'disposed_on' => 'date'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class)->orderBy('period');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class)->orderByDesc('date')->orderByDesc('id');
    }

    public function code(): string
    {
        return 'FA-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    /** Cost less what has been depreciated so far. */
    public function bookValue(): string
    {
        return Dec::sub($this->cost, $this->depreciated);
    }

    /** Straight line: the same amount every month until only the residual value is left. */
    public function monthly(): string
    {
        return Dec::div(Dec::sub($this->cost, $this->residual_value), (string) max(1, (int) $this->useful_life_months));
    }
}
