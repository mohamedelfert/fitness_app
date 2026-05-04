<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_log_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meal_log_id', 26);
            $table->char('food_id', 26);
            $table->enum('food_scope', ['global', 'tenant'])->default('global');
            $table->decimal('quantity_g', 7, 2);
            $table->decimal('kcal', 7, 2);
            $table->decimal('protein_g', 7, 2);
            $table->decimal('carbs_g', 7, 2);
            $table->decimal('fat_g', 7, 2);
            $table->timestamps();

            $table->foreign('meal_log_id')->references('id')->on('meal_logs')->onDelete('cascade');
            $table->index('meal_log_id');
            $table->index(['food_id', 'food_scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_log_items');
    }
};