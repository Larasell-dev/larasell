<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('larasell_categories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('larasell_products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_category_product');
    }
};
