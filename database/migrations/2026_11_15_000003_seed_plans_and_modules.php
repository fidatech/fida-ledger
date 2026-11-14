<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The two plans and the seven Business modules every installation starts with.
 * Prices and entitlements are data: the system administrators change them in the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (config('billing.plans') as $plan) {
            DB::table('plans')->insert($plan + ['currency' => config('billing.currency'), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (array_keys(config('modules')) as $key) {
            DB::table('modules')->insert(['key' => $key, 'is_available' => true, 'plans' => json_encode(['business']), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('modules')->delete();
        DB::table('plans')->delete();
    }
};
