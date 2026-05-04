<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_versions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->enum('kind', ['plan_initial', 'plan_replan', 'copilot']);
            $table->string('version', 20);
            $table->longText('template');
            $table->json('schema');
            $table->string('model', 60)->default('claude-opus-4-6');
            $table->decimal('temperature', 3, 2)->default(0.4);
            $table->integer('max_tokens')->default(8000);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['kind', 'version']);
            $table->index(['kind', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_versions');
    }
};