<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_monthly_summaries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->char('year_month', 7);
            $table->unsignedInteger('mau_count')->default(0);
            $table->unsignedInteger('authentication_count')->default(0);
            $table->unsignedInteger('user_created_count')->default(0);
            $table->unsignedInteger('token_issued_count')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'year_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_monthly_summaries');
    }
};
