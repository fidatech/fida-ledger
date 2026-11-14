<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A version of Fida Ledger a company subscribes to: Starter or Business. Prices are set in the admin panel. */
class Plan extends Model
{
    public const STARTER = 'starter';
    public const BUSINESS = 'business';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function onOffer()
    {
        return self::where('is_active', true)->orderBy('sort')->orderBy('id')->get();
    }
}
