<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_meals', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('plan_meal_day_id', 26);
            $table->enum('slot', ['breakfast', 'snack1', 'lunch', 'snack2', 'dinner', 'pre', 'post']);
            $table->string('title', 120);
            $table->decimal('target_kcal', 7, 2);
            $table->decimal('target_protein_g', 7, 2);
            $table->decimal('target_carbs_g', 7, 2);
            $table->decimal('target_fat_g', 7, 2);
            $table->timestamps();

            $table->foreign('plan_meal_day_id')->references('id')->on('plan_meal_days')->onDelete('cascade');
            $table->index('plan_meal_day_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_meals');
    }
};