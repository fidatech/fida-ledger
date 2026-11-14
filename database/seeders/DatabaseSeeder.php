<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Local development only: a system administrator to sign in with at /admin.
 * Plans and modules are created by the migrations, so production needs no seeding
 * (create its administrator with "php artisan app:create-admin").
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local', 'testing')) {
            Admin::firstOrCreate(['email' => 'admin@fidaledger.test'], ['name' => 'System Admin', 'password' => 'password123']);
        }
    }
}
