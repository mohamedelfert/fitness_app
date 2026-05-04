<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26);
            $table->char('parent_plan_id', 26)->nullable();
            $table->integer('version')->unsigned()->default(1);
            $table->enum('status', ['draft', 'pending_review', 'approved', 'active', 'archived', 'rejected'])->default('draft');
            $table->enum('source', ['manual', 'ai', 'template'])->default('manual');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->tinyInteger('weeks')->unsigned()->default(4);
            $table->text('notes')->nullable();
            $table->char('generated_by_user_id', 26);
            $table->char('approved_by_user_id', 26)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->char('ai_request_id', 26)->nullable();
            $table->json('ai_meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_profile_id', 'status', 'starts_on']);
            $table->index(['status', 'activated_at']);
            $table->index('parent_plan_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};