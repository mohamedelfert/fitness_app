<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_requests', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26)->nullable();
            $table->enum('kind', ['plan_initial', 'plan_replan', 'copilot']);
            $table->char('input_hash', 64);
            $table->enum('status', ['queued', 'running', 'succeeded', 'failed', 'validation_failed'])->default('queued');
            $table->string('model', 60);
            $table->string('version', 20);
            $table->json('input');
            $table->json('output')->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('cost_usd', 8, 4)->default(0);
            $table->integer('latency_ms')->nullable();
            $table->text('error')->nullable();
            $table->tinyInteger('retry_count')->default(0);
            $table->char('requested_by', 26);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['input_hash', 'created_at']);
            $table->index(['client_profile_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_requests');
    }
};