<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_social_providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('provider');
            $table->string('client_id');
            $table->text('client_secret');
            $table->boolean('enabled')->default(true);
            $table->json('scopes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'provider']);
            $table->index(['organization_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_social_providers');
    }
};
