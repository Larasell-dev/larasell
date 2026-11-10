<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->string('type', 64)->default('physical')->index();
        });

        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->string('type', 64)->nullable();
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->string('product_type', 64)->nullable();
            $table->json('capabilities')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'capabilities']);
        });

        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
