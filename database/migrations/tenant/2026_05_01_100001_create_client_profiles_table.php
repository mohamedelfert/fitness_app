<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('coach_user_id', 26)->nullable();
            $table->enum('sex', ['male', 'female', 'other']);
            $table->date('dob');
            $table->decimal('height_cm', 5, 2);
            $table->decimal('start_weight_kg', 6, 2);
            $table->decimal('target_weight_kg', 6, 2)->nullable();
            $table->date('target_date')->nullable();
            $table->enum('goal', ['fat_loss', 'maintain', 'muscle_gain', 'recomp', 'performance']);
            $table->enum('experience', ['beginner', 'intermediate', 'advanced']);
            $table->tinyInteger('training_days_per_week')->unsigned()->default(3);
            $table->smallInteger('session_duration_min')->unsigned()->default(60);
            $table->json('equipment');
            $table->json('injuries');
            $table->string('diet_preference', 40)->default('standard');
            $table->json('allergies');
            $table->json('disliked_foods');
            $table->text('coach_notes')->nullable();
            $table->json('internal_tags');
            $table->enum('status', ['onboarding', 'active', 'paused', 'archived'])->default('onboarding');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('user_id');
            $table->index(['coach_user_id', 'status']);
            $table->index(['status', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};