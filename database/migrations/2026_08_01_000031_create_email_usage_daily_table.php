<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_usage_daily', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('count')->default(0);

            $table->primary(['organization_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_usage_daily');
    }
};
