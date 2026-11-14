<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Document numbers: one counter per company and kind of document, taken under a row lock. */
class Sequence extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** The next number. Call it inside the transaction that saves the document, so a failed save leaves no gap. */
    public static function next(int $companyId, string $key): int
    {
        return DB::transaction(function () use ($companyId, $key) {
            self::firstOrCreate(['company_id' => $companyId, 'key' => $key], ['next' => 1]);
            $row = self::where('company_id', $companyId)->where('key', $key)->lockForUpdate()->first();
            $number = (int) $row->next;
            $row->update(['next' => $number + 1]);

            return $number;
        });
    }
}
