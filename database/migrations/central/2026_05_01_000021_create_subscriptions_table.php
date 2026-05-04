<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->char('user_id', 26)->nullable();
            $table->char('pricing_plan_id', 26);
            $table->string('type', 50)->default('default');
            $table->string('stripe_id', 60)->unique();
            $table->string('stripe_status', 40);
            $table->string('stripe_price', 60);
            $table->integer('quantity')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('pricing_plan_id')->references('id')->on('pricing_plans');
            $table->index(['tenant_id', 'stripe_status']);
            $table->index(['user_id', 'stripe_status']);

            // CHECK: Exactly one of tenant_id or user_id must be set
            $table->index('pricing_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};