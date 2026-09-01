<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->string('tax_price_mode')->nullable();
            $table->json('tax_total')->nullable();
            $table->json('tax_snapshot')->nullable();
            $table->json('shipping_tax_total')->nullable();
            $table->json('shipping_tax_snapshot')->nullable();
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->string('tax_category')->nullable();
            $table->json('taxable_amount')->nullable();
            $table->json('tax_total')->nullable();
            $table->json('tax_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropColumn(['tax_category', 'taxable_amount', 'tax_total', 'tax_snapshot']);
        });

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->dropColumn([
                'tax_price_mode',
                'tax_total',
                'tax_snapshot',
                'shipping_tax_total',
                'shipping_tax_snapshot',
            ]);
        });
    }
};
