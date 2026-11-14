<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The general ledger and the cash documents that post to it.
 * Money is DECIMAL(20,4) and rates DECIMAL(20,8): never a floating-point column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->char('code', 3);
            $table->string('name', 60);
            $table->string('symbol', 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        // one unit of "currency" is worth "rate" units of the company's base currency on "date"
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->date('date');
            $table->decimal('rate', 20, 8);
            $table->decimal('buy_rate', 20, 8)->nullable();
            $table->decimal('sell_rate', 20, 8)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'currency', 'date']);
        });

        // numbering of documents, one counter per company and kind of document
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->unsignedBigInteger('next')->default(1);
            $table->unique(['company_id', 'key']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('class', 10); // asset, liability, equity, income, expense
            $table->string('kind', 30)->nullable(); // cash, bank, customer ... (config/ledger.php); empty on a group
            $table->boolean('is_group')->default(false); // a heading of the chart: nothing is posted to it
            $table->string('system_key', 40)->nullable(); // how the code finds a special account
            $table->char('currency', 3)->nullable(); // cash and bank accounts hold one currency only
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'system_key']);
            $table->index(['company_id', 'kind']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('number');
            $table->date('date');
            $table->string('description')->nullable();
            $table->string('reference', 100)->nullable();
            // the document this entry was posted for (a money movement, a transfer, an invoice ...)
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            // the same request sent twice posts once
            $table->string('idempotency_key', 80)->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'date']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('date'); // the date of the entry, kept here so statements need no join
            $table->string('description')->nullable();
            $table->char('currency', 3);
            $table->decimal('debit', 20, 4)->default(0); // in "currency"
            $table->decimal('credit', 20, 4)->default(0);
            $table->decimal('rate', 20, 8)->default(1);
            $table->decimal('base_debit', 20, 4)->default(0); // in the base currency of the company
            $table->decimal('base_credit', 20, 4)->default(0);
            $table->index(['company_id', 'account_id', 'date']);
            $table->index(['company_id', 'date']);
        });

        Schema::create('money_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 3); // in, out
            $table->string('kind', 30); // config/ledger.php 'money'
            $table->unsignedBigInteger('number');
            $table->date('date');
            $table->foreignId('cash_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('counter_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 20, 4);
            $table->char('currency', 3);
            $table->decimal('rate', 20, 8)->default(1);
            $table->decimal('base_amount', 20, 4);
            $table->string('description')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('attachment_path')->nullable(); // on the private disk
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('posted'); // posted, void
            $table->string('idempotency_key', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'direction', 'number']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'direction', 'date']);
            $table->index(['company_id', 'counter_account_id']);
        });

        // money moved between two of the company's own cash or bank accounts; an exchange when the currencies differ
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // transfer, exchange
            $table->unsignedBigInteger('number');
            $table->date('date');
            $table->foreignId('from_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('from_amount', 20, 4);
            $table->char('from_currency', 3);
            $table->decimal('to_amount', 20, 4);
            $table->char('to_currency', 3);
            $table->decimal('rate', 20, 8)->default(1); // to_amount / from_amount
            $table->decimal('exchange_difference', 20, 4)->default(0); // base currency; a gain is positive
            $table->string('description')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('posted');
            $table->string('idempotency_key', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['transfers', 'money_movements', 'journal_lines', 'journal_entries', 'accounts', 'sequences', 'exchange_rates', 'currencies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
