<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_swaps', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workout_session_id', 26);
            $table->char('original_exercise_id', 26);
            $table->enum('original_exercise_scope', ['global', 'tenant']);
            $table->char('replacement_exercise_id', 26);
            $table->enum('replacement_exercise_scope', ['global', 'tenant']);
            $table->string('reason', 160)->nullable();
            $table->timestamps();

            $table->foreign('workout_session_id')->references('id')->on('workout_sessions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_swaps');
    }
};