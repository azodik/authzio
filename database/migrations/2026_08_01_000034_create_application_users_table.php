<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('oauth_client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedInteger('sign_in_count')->default(1);
            $table->timestamps();

            $table->unique(['organization_id', 'oauth_client_id', 'user_id'], 'application_users_unique');
            $table->index(['organization_id', 'last_seen_at']);
            $table->index(['oauth_client_id', 'last_seen_at']);
            $table->index(['user_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_users');
    }
};
