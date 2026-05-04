<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_meal_days', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('plan_id', 26);
            $table->tinyInteger('week_index');
            $table->tinyInteger('day_of_week');
            $table->decimal('total_kcal', 7, 2);
            $table->decimal('total_protein_g', 7, 2);
            $table->decimal('total_carbs_g', 7, 2);
            $table->decimal('total_fat_g', 7, 2);
            $table->timestamps();

            $table->unique(['plan_id', 'week_index', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_meal_days');
    }
};