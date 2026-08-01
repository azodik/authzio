<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_auth_codes', function (Blueprint $table): void {
            $table->string('id', 100)->primary();
            $table->foreignUuid('client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('scopes');
            $table->string('redirect_uri', 2048)->nullable();
            $table->string('nonce')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamp('expires_at');
            $table->string('code_challenge')->nullable();
            $table->string('code_challenge_method')->nullable();

            $table->index(['client_id', 'revoked']);
            $table->index(['user_id', 'expires_at']);
            $table->index(['revoked', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_auth_codes');
    }
};
