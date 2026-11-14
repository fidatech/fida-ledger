<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Banking & Planning (Business version): bank statements and reconciliation, budgets, recurring transactions. */
return new class extends Migration
{
    public function up(): void
    {
        // a line of the bank's own statement, imported from CSV, and the ledger line it was matched to
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('description')->nullable();
            $table->string('reference', 100)->nullable();
            $table->decimal('amount', 20, 4); // money into the account is positive, money out is negative
            // the same file imported twice adds nothing: (account, fingerprint) is unique
            $table->char('fingerprint', 40);
            // posted ledger lines are never changed, so the match is kept here; one ledger line matches one statement line
            $table->foreignId('journal_line_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['account_id', 'fingerprint']);
            $table->index(['company_id', 'account_id', 'date']);
        });

        // what the company plans to earn or spend on an account in a month
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7); // 2026-11
            $table->decimal('amount', 20, 4); // base currency
            $table->timestamps();
            $table->unique(['account_id', 'period']);
            $table->index(['company_id', 'period']);
        });

        // a Money In or Money Out that repeats: rent, a subscription, a standing income
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('direction', 3); // in, out
            $table->string('kind', 30);
            $table->foreignId('cash_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('counter_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 20, 4);
            $table->string('frequency', 10); // daily, weekly, monthly, yearly
            $table->date('next_run');
            $table->date('end_date')->nullable();
            // when runs were missed (server down, subscription lapsed): post every missed date, or only the latest one
            $table->string('missed_policy', 10)->default('all'); // all, latest
            $table->boolean('is_active')->default(true);
            $table->date('last_run')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'next_run']);
        });
    }

    public function down(): void
    {
        foreach (['recurring_transactions', 'budget_lines', 'bank_statement_lines'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
