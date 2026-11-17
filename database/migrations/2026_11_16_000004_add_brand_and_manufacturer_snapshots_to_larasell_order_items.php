<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What an order item keeps of its product's brand and manufacturer at checkout. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->json('brand_name')->nullable();
            $table->json('manufacturer')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'manufacturer']);
        });
    }
};
