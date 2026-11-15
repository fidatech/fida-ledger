<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The services list was removed: the system is for businesses that sell products.
     * Services already entered stay in the catalogue as products that are not kept in stock,
     * so the documents that used them keep working.
     */
    public function up(): void
    {
        DB::table('products')->where('type', 'service')->update(['type' => 'product', 'track_stock' => false]);
    }

    public function down(): void
    {
        // which products were services is not recorded, so there is nothing to turn back
    }
};
