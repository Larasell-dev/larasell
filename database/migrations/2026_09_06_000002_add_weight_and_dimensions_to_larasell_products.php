<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->json('weight')->nullable();
            $table->json('dimensions')->nullable();
        });

        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->json('weight')->nullable();
            $table->json('dimensions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->dropColumn(['weight', 'dimensions']);
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropColumn(['weight', 'dimensions']);
        });
    }
};
