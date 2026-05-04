<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_plans', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('slug', 40)->unique();
            $table->enum('audience', ['user', 'coach']);
            $table->string('name', 80);
            $table->string('stripe_product_id', 60);
            $table->string('stripe_price_id_monthly', 60);
            $table->string('stripe_price_id_annual', 60)->nullable();
            $table->integer('monthly_price_cents')->unsigned();
            $table->integer('annual_price_cents')->unsigned();
            $table->char('currency', 3)->default('USD');
            $table->json('features');
            $table->smallInteger('trial_days')->unsigned()->default(0);
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->timestamps();

            $table->index(['audience', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_plans');
    }
};