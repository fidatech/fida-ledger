<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry of the catalogue: a product. The same catalogue serves invoices, purchasing, stock and POS. */
class Product extends Model
{
    use BelongsToCompany;

    public const PRODUCT = 'product';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['track_stock' => 'boolean', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Only a product can be kept in stock; a service never moves inventory. */
    public function isStocked(): bool
    {
        return $this->type === self::PRODUCT && $this->track_stock;
    }
}
