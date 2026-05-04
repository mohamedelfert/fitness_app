<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26);
            $table->timestamp('logged_at');
            $table->enum('site', ['waist', 'hips', 'chest', 'arm_left', 'arm_right', 'thigh_left', 'thigh_right', 'neck']);
            $table->decimal('value_cm', 5, 2);
            $table->timestamps();

            $table->index(['client_profile_id', 'site', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};