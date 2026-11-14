<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use BelongsToCompany;

    protected $guarded = [];
}
