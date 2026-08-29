<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->json('discount_total')->nullable();
            $table->json('discounts')->nullable();
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->json('discount_total')->nullable();
        });

        DB::table('larasell_orders')->update([
            'discount_total' => json_encode(['amount' => '0'], JSON_THROW_ON_ERROR),
            'discounts' => json_encode([], JSON_THROW_ON_ERROR),
        ]);
        DB::table('larasell_order_items')->update([
            'discount_total' => json_encode(['amount' => '0'], JSON_THROW_ON_ERROR),
        ]);

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->json('discount_total')->nullable(false)->change();
            $table->json('discounts')->nullable(false)->change();
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->json('discount_total')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropColumn('discount_total');
        });

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->dropColumn(['discount_total', 'discounts']);
        });
    }
};
