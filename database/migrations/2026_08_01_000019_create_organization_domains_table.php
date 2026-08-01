<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_domains', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('host')->unique();
            $table->string('type');
            $table->boolean('is_primary')->default(false);
            $table->string('verification_token', 64)->nullable();
            $table->string('cloudflare_hostname_id')->nullable()->index();
            $table->string('cloudflare_status')->nullable();
            $table->string('cloudflare_ssl_status')->nullable();
            $table->json('dns_records')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_domains');
    }
};
