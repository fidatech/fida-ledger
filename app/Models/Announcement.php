<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Model;

/** A message from the platform shown on every company's dashboard (maintenance, a new feature, a price change). */
class Announcement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => Day::class, 'ends_on' => Day::class, 'is_active' => 'boolean'];
    }

    /** The announcements to show today. */
    public static function current()
    {
        $today = now()->toDateString();

        return self::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->orderByDesc('id')->limit(3)->get();
    }
}
