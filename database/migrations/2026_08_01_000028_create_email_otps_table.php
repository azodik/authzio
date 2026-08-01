<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_otps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->index();
            $table->string('code_hash');
            $table->string('purpose');
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('oauth_clients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['email', 'purpose', 'expires_at']);
            $table->index(['client_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otps');
    }
};
