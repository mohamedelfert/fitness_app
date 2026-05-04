<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('actor_user_id', 26)->nullable();
            $table->char('tenant_id', 26)->nullable();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->char('subject_id', 26)->nullable();
            $table->json('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};