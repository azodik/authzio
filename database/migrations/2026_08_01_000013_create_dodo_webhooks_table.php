<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dodo_webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('dodo_webhook_id')->nullable()->unique();
            $table->string('url');
            $table->text('secret')->nullable();
            $table->string('environment', 32)->default('test_mode');
            $table->string('description')->nullable();
            $table->json('filter_types')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamps();

            $table->index(['environment', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dodo_webhooks');
    }
};
