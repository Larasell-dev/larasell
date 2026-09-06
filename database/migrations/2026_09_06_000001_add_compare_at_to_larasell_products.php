<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->json('compare_at')->nullable();
        });

        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->json('compare_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->dropColumn('compare_at');
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropColumn('compare_at');
        });
    }
};
