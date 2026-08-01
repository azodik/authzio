<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_refresh_tokens', function (Blueprint $table): void {
            $table->string('id', 100)->primary();
            $table->string('access_token_id', 100);
            $table->boolean('revoked')->default(false);
            $table->timestamp('expires_at')->nullable();

            $table->foreign('access_token_id')
                ->references('id')
                ->on('oauth_access_tokens')
                ->cascadeOnDelete();

            $table->index(['revoked', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_refresh_tokens');
    }
};
