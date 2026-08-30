<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_product_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type')->default('text');
            $table->timestamps();
        });

        Schema::create('larasell_product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_attribute_id')->constrained('larasell_product_attributes')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->json('value');
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();

            $table->unique(['product_attribute_id', 'slug'], 'product_attribute_value_slug_unique');
            $table->index(['product_attribute_id', 'position'], 'product_attribute_value_position_idx');
        });

        Schema::create('larasell_product_product_attribute_value', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('larasell_products', indexName: 'product_attribute_value_product_fk')
                ->cascadeOnDelete();
            $table->foreignId('product_attribute_value_id')
                ->constrained('larasell_product_attribute_values', indexName: 'product_attribute_value_value_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'product_attribute_value_id'], 'product_attribute_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_product_product_attribute_value');
        Schema::dropIfExists('larasell_product_attribute_values');
        Schema::dropIfExists('larasell_product_attributes');
    }
};
