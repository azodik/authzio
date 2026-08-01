<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subdomain')->nullable()->unique();
            $table->string('primary_domain')->nullable();
            $table->string('billing_email')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('primary_domain');
            $table->index('is_demo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
