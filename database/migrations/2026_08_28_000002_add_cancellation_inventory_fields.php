<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->unsignedInteger('inventory_quantity')->default(0)->after('quantity');
        });

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->timestamp('inventory_restocked_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'inventory_restocked_at']);
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropColumn('inventory_quantity');
        });
    }
};
