<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_product_options', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type')->default('text');
            $table->timestamps();
        });

        Schema::create('larasell_product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained('larasell_product_options')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->json('value');
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();

            $table->unique(['product_option_id', 'slug']);
            $table->index(['product_option_id', 'position']);
        });

        Schema::create('larasell_product_product_option_value', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('larasell_products', indexName: 'product_option_value_product_fk')
                ->cascadeOnDelete();
            $table->foreignId('product_option_value_id')
                ->constrained('larasell_product_option_values', indexName: 'product_option_value_value_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'product_option_value_id'], 'product_option_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_product_product_option_value');
        Schema::dropIfExists('larasell_product_option_values');
        Schema::dropIfExists('larasell_product_options');
    }
};
