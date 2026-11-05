<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carts and orders remember where they were made. The models require a sales channel and locale; the
 * columns are nullable only because databases cannot add a required column to an existing table.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['larasell_carts', 'larasell_orders'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('sales_channel_id')->nullable()->constrained('larasell_sales_channels');
                $table->string('locale', 35)->nullable();
                $table->foreignId('entry_point_id')->nullable()->constrained('larasell_sales_channel_entry_points')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['larasell_carts', 'larasell_orders'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('entry_point_id');
                $table->dropConstrainedForeignId('sales_channel_id');
                $table->dropColumn('locale');
            });
        }
    }
};
