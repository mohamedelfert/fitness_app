<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_photos', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('client_profile_id', 26);
            $table->timestamp('taken_at');
            $table->enum('angle', ['front', 'side', 'back']);
            $table->string('s3_path', 255);
            $table->boolean('is_private')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_photos');
    }
};