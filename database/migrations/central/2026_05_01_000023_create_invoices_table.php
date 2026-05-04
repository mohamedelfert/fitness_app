<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('stripe_id', 60)->unique();
            $table->char('tenant_id', 26)->nullable();
            $table->char('user_id', 26)->nullable();
            $table->integer('amount_cents')->unsigned();
            $table->char('currency', 3);
            $table->enum('status', ['draft', 'open', 'paid', 'uncollectible', 'void']);
            $table->string('pdf_url', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};