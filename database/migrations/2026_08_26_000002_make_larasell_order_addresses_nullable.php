<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->json('billing_address')->nullable()->change();
            $table->json('shipping_address')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->json('billing_address')->nullable(false)->change();
            $table->json('shipping_address')->nullable(false)->change();
        });
    }
};
