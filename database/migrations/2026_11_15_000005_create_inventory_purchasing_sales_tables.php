<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Inventory, Purchasing and Sales Management (Business modules). Costs and stock values are in the base currency. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        // the costing state of a product in a warehouse: quantity on hand and what it cost in total (average = value / quantity)
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4)->default(0);
            $table->decimal('value', 20, 4)->default(0);
            $table->unique(['product_id', 'warehouse_id']);
            $table->index(['company_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('type', 20); // purchase, purchase_return, sale, sale_return, transfer_in, transfer_out, adjustment, opening
            $table->decimal('quantity', 20, 4); // in is positive, out is negative
            $table->decimal('value', 20, 4); // same sign as the quantity
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('description')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['company_id', 'product_id', 'date']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('number');
            $table->foreignId('supplier_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date');
            $table->date('expected_date')->nullable();
            $table->char('currency', 3);
            $table->decimal('rate', 20, 8)->default(1);
            $table->string('status', 12)->default('draft'); // draft, approved, closed, cancelled
            $table->decimal('total', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'date']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('received_qty', 20, 4)->default(0);
            $table->decimal('billed_qty', 20, 4)->default(0);
        });

        // one event of the purchase flow: goods received, a supplier bill, or goods sent back
        Schema::create('purchase_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('type', 10); // receipt, bill, return
            $table->unsignedBigInteger('number');
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->string('reference', 100)->nullable(); // the supplier's own invoice number
            $table->decimal('amount', 20, 4)->default(0); // in the currency of the order
            $table->json('lines'); // [{line_id, description, quantity, unit_price}]
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'type', 'number']);
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('order'); // quotation, order
            $table->unsignedBigInteger('number');
            $table->foreignId('customer_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date');
            $table->date('valid_until')->nullable();
            $table->char('currency', 3);
            $table->decimal('rate', 20, 8)->default(1);
            $table->string('status', 12)->default('draft'); // draft, confirmed, closed, cancelled
            $table->decimal('total', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'type', 'number']);
            $table->index(['company_id', 'type', 'status', 'date']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('income_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('discount', 20, 4)->default(0); // an amount off the whole line
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('delivered_qty', 20, 4)->default(0);
            $table->decimal('invoiced_qty', 20, 4)->default(0);
            $table->decimal('delivered_cost', 20, 4)->default(0); // what the delivered quantity cost, to value a return
        });

        Schema::create('sales_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->string('type', 10); // delivery, return
            $table->unsignedBigInteger('number');
            $table->date('date');
            $table->decimal('cost', 20, 4)->default(0); // base currency
            $table->json('lines');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'type', 'number']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('sales_order_id'));
        foreach (['sales_events', 'sales_order_lines', 'sales_orders', 'purchase_events', 'purchase_order_lines', 'purchase_orders', 'stock_movements', 'stock_levels', 'warehouses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
