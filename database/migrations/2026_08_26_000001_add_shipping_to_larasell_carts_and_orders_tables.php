<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_carts', function (Blueprint $table) {
            $table->string('shipping_option')->nullable();
        });

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->string('shipping_method')->nullable();
            $table->string('shipping_option')->nullable();
            $table->string('shipping_option_name')->nullable();
            $table->json('shipping_price')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_carts', function (Blueprint $table) {
            $table->dropColumn('shipping_option');
        });

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_method', 'shipping_option', 'shipping_option_name', 'shipping_price']);
        });
    }
};
