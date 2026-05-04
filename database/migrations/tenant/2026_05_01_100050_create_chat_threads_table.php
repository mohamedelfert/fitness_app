<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('coach_user_id', 26);
            $table->char('client_user_id', 26);
            $table->timestamp('last_message_at')->nullable();
            $table->smallInteger('unread_for_coach')->default(0);
            $table->smallInteger('unread_for_client')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['coach_user_id', 'client_user_id']);
            $table->index('last_message_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_threads');
    }
};