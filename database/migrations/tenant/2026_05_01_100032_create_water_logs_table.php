<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26);
            $table->timestamp('logged_at');
            $table->smallInteger('amount_ml')->unsigned();
            $table->timestamps();

            $table->index(['client_profile_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_logs');
    }
};