<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('application_type')->default('web');
            $table->text('description')->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->string('primary_color', 32)->default('#0F766E');
            $table->string('background_color', 32)->default('#F4F7F6');
            $table->string('login_headline')->nullable();
            $table->string('login_description', 500)->nullable();
            $table->string('login_button_label')->default('Continue');
            $table->boolean('show_signup_link')->default(true);
            $table->boolean('show_forgot_password_link')->default(true);
            $table->string('default_locale', 5)->default('en');
            $table->boolean('allow_locale_switch')->default(true);
            $table->string('login_layout', 32)->default('form_right');
            $table->string('login_theme', 16)->default('light');
            $table->json('password_policy')->nullable();
            $table->json('security_policy')->nullable();
            $table->json('login_methods')->nullable();
            $table->string('terms_url', 2048)->nullable();
            $table->string('privacy_url', 2048)->nullable();
            $table->boolean('require_legal_accept')->default(false);
            $table->string('secret')->nullable();
            $table->json('redirect_uris');
            $table->json('grant_types');
            $table->boolean('is_confidential');
            $table->boolean('is_first_party')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'revoked_at']);
            $table->index(['organization_id', 'name']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_clients');
    }
};
