<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_exercises', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('slug', 120)->unique();
            $table->string('name', 160);
            $table->string('primary_muscle', 40);
            $table->json('secondary_muscles');
            $table->string('equipment', 40);
            $table->enum('mechanic', ['compound', 'isolation']);
            $table->enum('force', ['push', 'pull', 'static']);
            $table->enum('level', ['beginner', 'intermediate', 'advanced']);
            $table->string('video_url', 255)->nullable();
            $table->string('thumbnail_path', 255)->nullable();
            $table->text('instructions')->nullable();
            $table->json('injury_tags');
            $table->integer('popularity')->unsigned()->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->fullText(['name', 'primary_muscle']);
            $table->index(['equipment', 'level', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_exercises');
    }
};