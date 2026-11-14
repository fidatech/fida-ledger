<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fixed Assets and Payroll (Business modules). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('useful_life_months')->default(60);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('name');
            $table->foreignId('asset_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('acquired_on');
            $table->decimal('cost', 20, 4); // base currency
            $table->decimal('residual_value', 20, 4)->default(0);
            $table->unsignedSmallInteger('useful_life_months');
            $table->string('method', 20)->default('straight_line');
            $table->decimal('depreciated', 20, 4)->default(0); // kept equal to the sum of asset_depreciations
            $table->string('location')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('status', 10)->default('active'); // active, disposed
            $table->date('disposed_on')->nullable();
            $table->decimal('disposal_proceeds', 20, 4)->nullable();
            $table->foreignId('acquisition_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('disposal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });

        // one row per asset and month: the unique key is what makes a depreciation run safe to repeat
        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained()->restrictOnDelete();
            $table->char('period', 7); // 2026-11
            $table->decimal('amount', 20, 4);
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['fixed_asset_id', 'period']);
        });

        // maintenance and moves of an asset: a log, nothing is posted (pay a repair with Money Out)
        Schema::create('asset_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12); // maintenance, transfer
            $table->date('date');
            $table->string('description');
            $table->decimal('cost', 20, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('phone', 50)->nullable();
            $table->date('joined_on')->nullable();
            $table->decimal('basic_salary', 20, 4)->default(0); // per month, base currency
            $table->decimal('allowances', 20, 4)->default(0); // per month
            $table->decimal('deductions', 20, 4)->default(0); // per month: tax, pension ... as configured by the company
            $table->decimal('advance_balance', 20, 4)->default(0); // salary advances not yet recovered
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->decimal('amount', 20, 4);
            $table->foreignId('paid_from_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7); // 2026-11
            $table->string('status', 10)->default('draft'); // draft, approved, paid
            $table->decimal('gross', 20, 4)->default(0);
            $table->decimal('deductions', 20, 4)->default(0);
            $table->decimal('advances', 20, 4)->default(0);
            $table->decimal('net', 20, 4)->default(0);
            $table->foreignId('accrual_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('payment_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'period']); // one payroll per month: a second run is impossible
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('basic', 20, 4)->default(0);
            $table->decimal('allowances', 20, 4)->default(0);
            $table->decimal('deductions', 20, 4)->default(0);
            $table->decimal('advance', 20, 4)->default(0); // recovered from this salary
            $table->decimal('net', 20, 4)->default(0);
            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        foreach (['payroll_lines', 'payroll_runs', 'salary_advances', 'employees', 'departments', 'asset_events', 'asset_depreciations', 'fixed_assets', 'asset_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
