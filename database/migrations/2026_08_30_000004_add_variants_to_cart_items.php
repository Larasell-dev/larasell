<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('status');
        });

        Schema::table('larasell_cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id']);
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('larasell_product_variants')
                ->cascadeOnDelete();
        });

        DB::table('larasell_products')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('larasell_product_variants')
                ->whereColumn('larasell_product_variants.product_id', 'larasell_products.id'))
            ->orderBy('id')
            ->each(function (object $product): void {
                DB::table('larasell_product_variants')->insert([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'allow_backorders' => $product->allow_backorders,
                    'min_quantity' => $product->min_quantity,
                    'max_quantity' => $product->max_quantity,
                    'status' => $product->status,
                    'is_default' => true,
                    'position' => 0,
                    'combination_key' => 'default',
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ]);
            });

        DB::table('larasell_cart_items')->orderBy('id')->each(function (object $item): void {
            $variantId = DB::table('larasell_product_variants')
                ->where('product_id', $item->product_id)
                ->where('is_default', true)
                ->value('id');

            if ($variantId === null) {
                throw new RuntimeException("Cart item [{$item->id}] has no default product variant.");
            }

            DB::table('larasell_cart_items')->where('id', $item->id)->update([
                'product_variant_id' => $variantId,
            ]);
        });

        Schema::table('larasell_cart_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variant_id')->nullable(false)->change();
            $table->unique(['cart_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('larasell_cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_variant_id']);
            $table->dropConstrainedForeignId('product_variant_id');
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
