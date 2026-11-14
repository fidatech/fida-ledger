<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Point of Sale (Business module). A POS sale is an invoice, a stock issue and Money In made by the shared services. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained('accounts')->restrictOnDelete(); // the cash drawer
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_register_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 8)->default('open'); // open, closed
            $table->timestamp('opened_at');
            $table->decimal('opening_cash', 20, 4)->default(0); // counted in the drawer when the shift starts
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 20, 4)->nullable();
            $table->decimal('counted_cash', 20, 4)->nullable();
            $table->decimal('difference', 20, 4)->nullable(); // counted - expected: negative is a shortage
            $table->foreignId('difference_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'pos_register_id', 'status']);
        });

        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_session_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('total', 20, 4);
            $table->decimal('cash_paid', 20, 4)->default(0); // the part that went into the drawer
            $table->decimal('cost', 20, 4)->default(0); // cost of the goods handed over
            $table->json('items'); // [{product_id, name, quantity, unit_price, discount, cost}]
            $table->json('payments'); // [{account_id, name, amount, movement_id}]
            $table->string('status', 10)->default('paid'); // paid, refunded
            $table->foreignId('cost_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('idempotency_key', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['pos_session_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['pos_sales', 'pos_sessions', 'pos_registers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
