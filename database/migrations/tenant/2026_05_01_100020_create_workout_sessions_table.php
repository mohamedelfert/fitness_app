<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26);
            $table->char('plan_workout_day_id', 26)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('total_volume_kg', 10, 2)->default(0);
            $table->tinyInteger('perceived_effort')->nullable();
            $table->text('notes')->nullable();
            $table->enum('source', ['app', 'wearable', 'manual'])->default('app');
            $table->char('idempotency_key', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_profile_id', 'started_at']);
            $table->unique('idempotency_key');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};