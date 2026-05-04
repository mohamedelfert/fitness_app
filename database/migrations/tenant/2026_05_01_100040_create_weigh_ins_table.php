<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weigh_ins', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26);
            $table->timestamp('logged_at');
            $table->decimal('weight_kg', 6, 2);
            $table->decimal('body_fat_pct', 4, 1)->nullable();
            $table->enum('source', ['app', 'wearable', 'manual'])->default('app');
            $table->timestamps();

            $table->index(['client_profile_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weigh_ins');
    }
};