<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('set_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workout_session_id', 26);
            $table->char('plan_exercise_id', 26)->nullable();
            $table->char('exercise_id', 26);
            $table->enum('exercise_scope', ['global', 'tenant'])->default('global');
            $table->tinyInteger('set_index');
            $table->smallInteger('reps')->unsigned()->default(0);
            $table->decimal('weight_kg', 6, 2)->default(0);
            $table->tinyInteger('rpe')->nullable();
            $table->boolean('is_warmup')->default(false);
            $table->timestamp('completed_at');
            $table->char('idempotency_key', 36)->nullable();

            $table->foreign('workout_session_id')->references('id')->on('workout_sessions')->onDelete('cascade');
            $table->index(['workout_session_id', 'set_index']);
            $table->index(['exercise_id', 'exercise_scope', 'completed_at']);
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_logs');
    }
};