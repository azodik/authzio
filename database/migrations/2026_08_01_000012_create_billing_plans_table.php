<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('mau_limit');
            $table->unsignedInteger('application_limit')->nullable();
            $table->unsignedInteger('price_cents_monthly')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('dodo_product_id')->nullable()->index();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_self_serve')->default(true);
            $table->boolean('allows_custom_domains')->default(false);
            $table->boolean('allows_email_customization')->default(false);
            $table->boolean('allows_login_customization')->default(true);
            $table->boolean('allows_custom_jwks')->default(false);
            $table->boolean('allows_custom_email_provider')->default(false);
            $table->boolean('allows_sso')->default(false);
            $table->unsignedInteger('email_daily_limit')->nullable();
            $table->unsignedInteger('email_monthly_limit')->nullable();
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->index(['is_public', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plans');
    }
};
