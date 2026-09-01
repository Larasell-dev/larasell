<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->string('tax_category')->default('standard');
        });

        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->string('tax_category')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->dropColumn('tax_category');
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropColumn('tax_category');
        });
    }
};
