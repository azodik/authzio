<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_signing_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('kid', 64);
            $table->string('alg', 16)->default('RS256');
            $table->json('public_jwk');
            $table->text('private_key');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'kid']);
            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_signing_keys');
    }
};
