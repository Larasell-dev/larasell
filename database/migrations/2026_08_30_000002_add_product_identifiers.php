<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->string('product_sku')->nullable();
            $table->string('product_barcode')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropColumn(['product_sku', 'product_barcode']);
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropUnique(['barcode']);
            $table->dropColumn(['sku', 'barcode']);
        });
    }
};
