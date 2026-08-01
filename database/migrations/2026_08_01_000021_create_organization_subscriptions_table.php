<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->unique()->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('billing_plan_id')->constrained('billing_plans')->restrictOnDelete();
            $table->string('status')->index();
            $table->string('dodo_customer_id')->nullable()->index();
            $table->string('dodo_subscription_id')->nullable()->unique();
            $table->string('dodo_checkout_session_id')->nullable()->index();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['billing_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_subscriptions');
    }
};
