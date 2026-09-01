<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_variant_id']);
            $table->json('metadata')->nullable()->after('quantity');
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('quantity');
        });

        DB::table('larasell_cart_items')->update(['metadata' => '[]']);
        DB::table('larasell_order_items')->update(['metadata' => '[]']);

        Schema::table('larasell_cart_items', function (Blueprint $table) {
            $table->json('metadata')->nullable(false)->change();
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->json('metadata')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_cart_items', function (Blueprint $table) {
            $table->dropColumn('metadata');
            $table->unique(['cart_id', 'product_variant_id']);
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
