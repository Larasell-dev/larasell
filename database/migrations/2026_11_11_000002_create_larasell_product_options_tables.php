<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Options decide which variants a product has: global definitions, the ones a product uses, and each variant's values. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_option_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->string('display', 16)->default('dropdown');
            $table->timestamps();
        });

        Schema::create('larasell_option_definition_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_definition_id')->constrained('larasell_option_definitions')->cascadeOnDelete();
            $table->string('slug');
            $table->json('name');
            $table->string('swatch', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['option_definition_id', 'slug'], 'option_definition_value_slug_unique');
        });

        Schema::create('larasell_product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('larasell_products')->cascadeOnDelete();
            $table->foreignId('option_definition_id')->constrained('larasell_option_definitions')->restrictOnDelete();
            $table->unsignedInteger('position');

            $table->unique(['product_id', 'option_definition_id']);
        });

        Schema::create('larasell_product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained('larasell_product_options')->cascadeOnDelete();
            $table->foreignId('option_definition_value_id')->constrained('larasell_option_definition_values', indexName: 'product_option_value_definition_fk')->restrictOnDelete();
            $table->unsignedInteger('position');

            $table->unique(['product_option_id', 'option_definition_value_id'], 'product_option_value_unique');
        });

        Schema::create('larasell_product_variant_option_values', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained('larasell_product_variants', indexName: 'variant_option_value_variant_fk')->cascadeOnDelete();
            $table->foreignId('product_option_id')->constrained('larasell_product_options', indexName: 'variant_option_value_option_fk')->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained('larasell_product_option_values', indexName: 'variant_option_value_value_fk')->restrictOnDelete();

            $table->primary(['product_variant_id', 'product_option_id'], 'variant_option_value_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_product_variant_option_values');
        Schema::dropIfExists('larasell_product_option_values');
        Schema::dropIfExists('larasell_product_options');
        Schema::dropIfExists('larasell_option_definition_values');
        Schema::dropIfExists('larasell_option_definitions');
    }
};
