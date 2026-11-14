<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Products & Services (Business module "products") and invoicing (part of every plan). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('symbol', 20)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('product'); // product, service
            $table->string('name');
            $table->string('sku', 60)->nullable();
            $table->string('barcode', 60)->nullable();
            $table->foreignId('product_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('purchase_price', 20, 4)->default(0);
            $table->decimal('sale_price', 20, 4)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0); // percent
            // a service never moves stock, whatever this says
            $table->boolean('track_stock')->default(true);
            $table->decimal('reorder_level', 20, 4)->default(0);
            $table->foreignId('income_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'barcode']);
            $table->index(['company_id', 'type']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12)->default('invoice'); // invoice, credit_note
            $table->unsignedBigInteger('number');
            $table->string('status', 10)->default('draft'); // draft, issued, void
            $table->foreignId('customer_account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->char('currency', 3);
            $table->decimal('rate', 20, 8)->default(1);
            $table->decimal('subtotal', 20, 4)->default(0); // after line discounts, before tax
            $table->decimal('tax', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('paid', 20, 4)->default(0); // settled by Money In, kept equal to the allocations
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'type', 'number']);
            $table->index(['company_id', 'status', 'date']);
            $table->index(['company_id', 'customer_account_id']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('income_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 20, 4)->default(1);
            $table->decimal('unit_price', 20, 4)->default(0);
            $table->decimal('discount', 20, 4)->default(0); // an amount taken off this line
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('net', 20, 4)->default(0); // quantity x price - discount
            $table->decimal('tax', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
        });

        // which Money In settled which invoice, and for how much
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('money_movement_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 20, 4);
            $table->timestamps();
            $table->unique(['invoice_id', 'money_movement_id']);
        });
    }

    public function down(): void
    {
        foreach (['invoice_payments', 'invoice_lines', 'invoices', 'products', 'units', 'product_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
