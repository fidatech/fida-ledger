<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platform-wide switch of an optional module and the plans that include it (config/modules.php has the rest). */
class Module extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_available' => 'boolean', 'plans' => 'array'];
    }
}
