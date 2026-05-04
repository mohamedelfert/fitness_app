<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('thread_id', 26);
            $table->char('sender_user_id', 26);
            $table->text('body');
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_mime', 60)->nullable();
            $table->smallInteger('voice_duration_sec')->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();

            $table->foreign('thread_id')->references('id')->on('chat_threads')->onDelete('cascade');
            $table->index(['thread_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};