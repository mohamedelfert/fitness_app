<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_notes', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26);
            $table->char('author_user_id', 26);
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->foreign('client_profile_id')->references('id')->on('client_profiles')->onDelete('cascade');
            $table->index(['client_profile_id', 'is_pinned', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notes');
    }
};