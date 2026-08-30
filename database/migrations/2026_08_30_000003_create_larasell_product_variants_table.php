<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Larasell\Larasell\Enums\Visibility;

return new class extends Migration
{
    public function up(): void
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

        Schema::create('larasell_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('larasell_products')->cascadeOnDelete();
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();
            $table->json('price')->nullable();
            $table->integer('stock')->nullable();
            $table->boolean('allow_backorders')->nullable();
            $table->unsignedInteger('min_quantity')->nullable();
            $table->unsignedInteger('max_quantity')->nullable();
            $table->string('status')->default(Visibility::Visible->value);
            $table->unsignedInteger('position')->default(0);
            $table->string('combination_key');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'combination_key']);
            $table->index(['product_id', 'status', 'position']);
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

    public function down(): void
    {
        Schema::dropIfExists('larasell_product_variant_product_attribute_value');
        Schema::dropIfExists('larasell_product_variants');
        Schema::dropIfExists('larasell_product_variant_dimensions');
    }
};
