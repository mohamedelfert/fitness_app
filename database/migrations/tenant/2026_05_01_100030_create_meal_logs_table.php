<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26);
            $table->timestamp('logged_at');
            $table->enum('slot', ['breakfast', 'snack1', 'lunch', 'snack2', 'dinner', 'pre', 'post']);
            $table->char('plan_meal_id', 26)->nullable();
            $table->enum('source', ['plan', 'manual', 'barcode', 'photo'])->default('manual');
            $table->text('notes')->nullable();
            $table->decimal('total_kcal', 7, 2)->default(0);
            $table->decimal('total_protein_g', 7, 2)->default(0);
            $table->decimal('total_carbs_g', 7, 2)->default(0);
            $table->decimal('total_fat_g', 7, 2)->default(0);
            $table->char('idempotency_key', 36)->nullable();
            $table->timestamps();

            $table->index(['client_profile_id', 'logged_at']);
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_logs');
    }
};