<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Variants inherit, override or clear the product's weight, dimensions, customs data, type,
 * name and description, each in its own *_inheritance column (name and description per locale).
 * Identifiers move to the variants only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->string('country_of_origin', 2)->nullable();
            $table->string('hs_code', 16)->nullable();
        });

        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->string('mpn')->nullable()->index();
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->string('weight_inheritance', 16)->default('inherited');
            $table->string('dimensions_inheritance', 16)->default('inherited');
            $table->string('country_of_origin', 2)->nullable();
            $table->string('country_of_origin_inheritance', 16)->default('inherited');
            $table->string('hs_code', 16)->nullable();
            $table->string('hs_code_inheritance', 16)->default('inherited');
            $table->string('type_inheritance', 16)->default('inherited');
        });

        // The default variant held copies of the product's weight and dimensions; it inherits them now.
        DB::table('larasell_product_variants')->where('is_default', true)->update(['weight' => null, 'dimensions' => null]);

        foreach (['weight', 'dimensions', 'type'] as $field) {
            DB::table('larasell_product_variants')->whereNotNull($field)->update(["{$field}_inheritance" => 'overridden']);
        }

        DB::table('larasell_products')
            ->where(fn ($query) => $query->whereNotNull('sku')->orWhereNotNull('barcode'))
            ->orderBy('id')
            ->each(function (object $product): void {
                $variant = DB::table('larasell_product_variants')->where('product_id', $product->id)->where('is_default', true);

                foreach (['sku', 'barcode'] as $identifier) {
                    if ($product->{$identifier} !== null) {
                        (clone $variant)->whereNull($identifier)->update([$identifier => $product->{$identifier}]);
                    }
                }
            });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropUnique(['barcode']);
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropColumn(['sku', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();
        });

        DB::table('larasell_product_variants')->where('is_default', true)->orderBy('id')->each(function (object $variant): void {
            DB::table('larasell_products')->where('id', $variant->product_id)->update(['sku' => $variant->sku, 'barcode' => $variant->barcode]);
        });

        DB::table('larasell_product_variants')->where('type_inheritance', '!=', 'overridden')->update(['type' => null]);

        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->dropIndex(['mpn']);
        });

        Schema::table('larasell_product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'mpn', 'name', 'description',
                'weight_inheritance', 'dimensions_inheritance',
                'country_of_origin', 'country_of_origin_inheritance',
                'hs_code', 'hs_code_inheritance', 'type_inheritance',
            ]);
        });

        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropColumn(['country_of_origin', 'hs_code']);
        });
    }
};
