<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('slug', 60)->unique();
            $table->string('name', 120);
            $table->enum('type', ['solo_coach', 'gym', 'enterprise'])->default('solo_coach');
            $table->string('subdomain', 60)->unique();
            $table->string('custom_domain', 190)->unique()->nullable();
            $table->string('db_name', 64);
            $table->string('db_host', 190);
            $table->string('logo_path', 255)->nullable();
            $table->char('primary_color', 7)->default('#0EA5E9');
            $table->char('secondary_color', 7)->default('#111827');
            $table->string('font', 60)->default('Inter');
            $table->enum('status', ['trial', 'active', 'past_due', 'suspended', 'closed'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->char('owner_user_id', 26);
            $table->string('stripe_customer_id', 60)->nullable();
            $table->json('settings')->default('{}');
            $table->timestamps();

            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('owner_user_id');
            $table->index('status');
            $table->index('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};