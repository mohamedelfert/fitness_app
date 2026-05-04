<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_exercises', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('plan_workout_day_id', 26);
            $table->char('exercise_id', 26);
            $table->enum('exercise_scope', ['global', 'tenant'])->default('global');
            $table->tinyInteger('order')->default(1);
            $table->tinyInteger('target_sets')->default(3);
            $table->string('target_reps', 15)->default('8-10');
            $table->decimal('target_weight_kg', 6, 2)->nullable();
            $table->tinyInteger('target_rpe')->nullable();
            $table->smallInteger('target_rest_sec')->unsigned()->default(90);
            $table->text('notes')->nullable();
            $table->char('superset_group_id', 26)->nullable();
            $table->boolean('is_optional')->default(false);
            $table->timestamps();

            $table->foreign('plan_workout_day_id')->references('id')->on('plan_workout_days')->onDelete('cascade');
            $table->index(['plan_workout_day_id', 'order']);
            $table->index(['exercise_id', 'exercise_scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_exercises');
    }
};