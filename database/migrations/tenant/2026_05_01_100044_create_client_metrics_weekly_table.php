<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_metrics_weekly', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26);
            $table->date('week_start');
            $table->integer('workouts_done')->default(0);
            $table->integer('workouts_planned')->default(0);
            $table->decimal('kcal_avg', 7, 2)->default(0);
            $table->decimal('kcal_target', 7, 2)->default(0);
            $table->decimal('protein_avg_g', 7, 2)->default(0);
            $table->decimal('adherence_pct', 5, 2)->default(0);
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('weight_delta_kg', 5, 2)->default(0);
            $table->timestamp('recomputed_at');
            $table->timestamps();

            $table->unique(['client_profile_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_metrics_weekly');
    }
};