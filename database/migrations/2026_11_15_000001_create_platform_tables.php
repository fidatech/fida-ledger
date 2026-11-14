<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The platform: administrators, plans, modules, users, companies and their subscriptions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 20)->default('super'); // super, manager, viewer
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // starter, business
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('setup_fee', 12, 2)->default(0); // charged once per company
            $table->decimal('annual_fee', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('max_users')->nullable(); // empty = no limit
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // which optional modules exist on the platform and which plans may use them
        Schema::create('modules', function (Blueprint $table) {
            $table->string('key', 30)->primary();
            $table->boolean('is_available')->default(true);
            $table->json('plans'); // codes of the plans that include the module
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('locale', 5)->default('en');
            $table->boolean('is_active')->default(true);
            // the company opened after sign-in: the one the user worked in last
            $table->unsignedBigInteger('last_company_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30)->default('other');
            $table->char('base_currency', 3);
            $table->string('country', 100)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 12)->default('pending'); // pending, active, suspended
            $table->boolean('setup_fee_paid')->default(false);
            $table->date('subscription_ends_at')->nullable();
            // nothing may be posted on or before this day (period lock)
            $table->date('locked_until')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('viewer');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('company_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('module', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'module']);
        });

        // a company says it has paid; an administrator approves or rejects. Only an approval activates.
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('kind', 12); // first, renewal
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->decimal('annual_fee', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('receipt_path')->nullable(); // on the private disk
            $table->string('status', 12)->default('pending'); // pending, approved, rejected
            $table->string('rejection_reason', 30)->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject')->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'subscription_payments', 'company_modules', 'company_user', 'companies', 'password_reset_tokens', 'users', 'modules', 'plans', 'admins'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
