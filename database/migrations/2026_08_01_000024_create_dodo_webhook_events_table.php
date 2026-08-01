<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dodo_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('dodo_webhook_id')->nullable()->constrained('dodo_webhooks')->nullOnDelete();
            $table->string('webhook_id')->unique();
            $table->string('event_type')->index();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->index(['dodo_webhook_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dodo_webhook_events');
    }
};
