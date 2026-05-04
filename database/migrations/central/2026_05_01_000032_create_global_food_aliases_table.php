<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_food_aliases', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('food_id', 26);
            $table->string('alias', 190);
            $table->char('locale', 5)->default('en');
            $table->timestamps();

            $table->foreign('food_id')->references('id')->on('global_foods')->onDelete('cascade');
            $table->fullText('alias');
            $table->index(['food_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_food_aliases');
    }
};