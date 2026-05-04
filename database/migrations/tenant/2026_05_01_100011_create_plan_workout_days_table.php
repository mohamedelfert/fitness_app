<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_workout_days', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('plan_id', 26);
            $table->tinyInteger('week_index');
            $table->tinyInteger('day_of_week');
            $table->tinyInteger('session_order')->default(1);
            $table->string('session_label', 60)->nullable();
            $table->string('title', 120);
            $table->string('focus', 60);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            $table->index(['plan_id', 'week_index', 'day_of_week', 'session_order'], 'pwd_skel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_workout_days');
    }
};