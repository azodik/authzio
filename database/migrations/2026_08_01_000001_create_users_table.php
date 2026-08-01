<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('avatar_url', 2048)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('preferred_locale', 5)->default('en');
            $table->string('theme', 16)->default('system');
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->boolean('mfa_enabled')->default(false);
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['is_active', 'is_demo']);
            $table->index('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
