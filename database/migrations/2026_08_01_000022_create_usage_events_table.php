<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('subject_key');
            $table->date('occurred_on');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['organization_id', 'subject_key', 'occurred_on', 'event_type'], 'usage_events_dedupe');
            $table->index(['organization_id', 'occurred_on']);
            $table->index(['organization_id', 'event_type', 'occurred_on']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
