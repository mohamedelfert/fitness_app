<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_templates', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name', 120);
            $table->json('body');
            $table->boolean('is_global')->default(false);
            $table->char('created_by_user_id', 26);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_templates');
    }
};