<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One optional module of one company, switched on or off. Switching off never deletes records. */
class CompanyModule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
