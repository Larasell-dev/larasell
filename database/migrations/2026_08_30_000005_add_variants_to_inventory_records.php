<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('larasell_product_variants', indexName: 'inventory_reservation_variant_fk')
                ->nullOnDelete();
        });

        Schema::table('larasell_inventory_reservations', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('larasell_product_variants')
                ->nullOnDelete();
        });

        DB::table('larasell_order_items')
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->each(function (object $item): void {
                $variantId = DB::table('larasell_product_variants')
                    ->where('product_id', $item->product_id)
                    ->where('is_default', true)
                    ->value('id');

                if ($variantId !== null) {
                    DB::table('larasell_order_items')->where('id', $item->id)->update([
                        'product_variant_id' => $variantId,
                    ]);
                }
            });

        DB::table('larasell_inventory_reservations')
            ->orderBy('id')
            ->each(function (object $reservation): void {
                $variantId = DB::table('larasell_order_items')
                    ->where('id', $reservation->order_item_id)
                    ->value('product_variant_id');

                if ($variantId !== null) {
                    DB::table('larasell_inventory_reservations')->where('id', $reservation->id)->update([
                        'product_variant_id' => $variantId,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('larasell_inventory_reservations', function (Blueprint $table) {
            $table->dropForeign('inventory_reservation_variant_fk');
            $table->dropColumn('product_variant_id');
        });

        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
        });
    }
};
