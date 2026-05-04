<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('subscription_id', 26);
            $table->string('stripe_id', 60)->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};