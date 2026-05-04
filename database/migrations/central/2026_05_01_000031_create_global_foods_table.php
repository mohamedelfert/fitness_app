<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_foods', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name', 190);
            $table->string('brand', 120)->nullable();
            $table->decimal('serving_size_g', 7, 2)->default(100);
            $table->decimal('kcal', 7, 2)->default(0);
            $table->decimal('protein_g', 7, 2)->default(0);
            $table->decimal('carbs_g', 7, 2)->default(0);
            $table->decimal('fat_g', 7, 2)->default(0);
            $table->decimal('fiber_g', 7, 2)->default(0);
            $table->decimal('sugar_g', 7, 2)->default(0);
            $table->decimal('sodium_mg', 8, 2)->default(0);
            $table->string('barcode', 20)->nullable();
            $table->enum('source', ['curated', 'usda', 'off'])->default('curated');
            $table->json('allergen_tags');
            $table->json('diet_tags');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('barcode');
            $table->fullText(['name', 'brand']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_foods');
    }
};