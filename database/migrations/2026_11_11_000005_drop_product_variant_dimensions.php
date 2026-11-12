<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Variants are made from options now; the previous migration moved the dimensions and their values to them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('larasell_product_variant_product_attribute_value');
        Schema::dropIfExists('larasell_product_variant_dimensions');
    }

    public function down(): void
    {
        Schema::create('larasell_product_variant_dimensions', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('larasell_products')->cascadeOnDelete();
            $table->foreignId('product_attribute_id')
                ->constrained('larasell_product_attributes', indexName: 'product_variant_dimension_attribute_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('position');

            $table->primary(['product_id', 'product_attribute_id'], 'product_variant_dimension_primary');
            $table->unique(['product_id', 'position'], 'product_variant_dimension_position_unique');
        });

        Schema::create('larasell_product_variant_product_attribute_value', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->constrained('larasell_product_variants', indexName: 'product_variant_value_variant_fk')
                ->cascadeOnDelete();
            $table->foreignId('product_attribute_value_id')
                ->constrained('larasell_product_attribute_values', indexName: 'product_variant_value_value_fk')
                ->cascadeOnDelete();

            $table->primary(
                ['product_variant_id', 'product_attribute_value_id'],
                'product_variant_value_primary',
            );
        });
    }
};
