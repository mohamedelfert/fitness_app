<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_records', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26);
            $table->char('exercise_id', 26);
            $table->enum('exercise_scope', ['global', 'tenant'])->default('global');
            $table->enum('kind', ['1rm', 'max_reps', 'max_volume', 'max_weight']);
            $table->decimal('value', 8, 2);
            $table->timestamp('achieved_at');
            $table->char('set_log_id', 26)->nullable();
            $table->timestamps();

            $table->index(['client_profile_id', 'exercise_id', 'kind', 'achieved_at'], 'pr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_records');
    }
};