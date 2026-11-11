<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Attributes say whether they describe products, variants or both, and variants override or clear inherited ones. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_product_attributes', function (Blueprint $table) {
            $table->string('scope', 16)->default('product');
            $table->string('cardinality', 16)->default('single');
        });

        // One row per own value; an override without values is one row without a value.
        Schema::create('larasell_product_variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('larasell_product_variants', indexName: 'variant_attribute_variant_fk')->cascadeOnDelete();
            $table->foreignId('product_attribute_id')->constrained('larasell_product_attributes', indexName: 'variant_attribute_attribute_fk')->cascadeOnDelete();
            $table->foreignId('product_attribute_value_id')->nullable()->constrained('larasell_product_attribute_values', indexName: 'variant_attribute_value_fk')->cascadeOnDelete();
            $table->string('state', 16);

            $table->unique(['product_variant_id', 'product_attribute_id', 'product_attribute_value_id'], 'variant_attribute_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_product_variant_attribute_values');

        Schema::table('larasell_product_attributes', function (Blueprint $table) {
            $table->dropColumn(['scope', 'cardinality']);
        });
    }
};
