<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A unit of measure: piece, kg, box, hour ... */
class Unit extends Model
{
    use BelongsToCompany;

    protected $guarded = [];
}
