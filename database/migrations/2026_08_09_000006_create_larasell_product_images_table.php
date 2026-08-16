<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_product_images', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('larasell_product_product_image', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('larasell_products')->cascadeOnDelete();
            $table->foreignId('product_image_id')->constrained('larasell_product_images')->cascadeOnDelete();
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'product_image_id']);
            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_product_product_image');
        Schema::dropIfExists('larasell_product_images');
    }
};
