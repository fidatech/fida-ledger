<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the language a person reads the system in (English, Dari, Pashto)
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable();
        });

        // one WhatsApp connection (RelayWA) per company
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('session_key')->nullable(); // encrypted; never shown again after it is saved
            $table->boolean('is_enabled')->default(false);
            $table->string('country_code', 5)->nullable(); // put in front of numbers written as 07...
            $table->json('templates')->nullable(); // only the texts the company changed
            $table->timestamps();
        });

        // every message the system handed to RelayWA, and what RelayWA answered
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 32);
            $table->string('kind', 20)->default('custom'); // invoice, receipt, statement, test, custom
            $table->string('reference', 100)->nullable(); // the document it was about
            $table->text('body');
            $table->string('status', 10); // accepted (RelayWA took it), failed
            $table->string('provider_id', 190)->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
        });

        // keys for other programs (a mobile or desktop app) to work in ONE company as ONE user
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->char('token_hash', 64)->unique(); // SHA-256 of the key; the key itself is never stored
            $table->boolean('can_write')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
