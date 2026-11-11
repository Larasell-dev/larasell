<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Attributes that were a product's variant dimensions become option definitions, with their values;
 * products and variants keep the same combinations through the new option tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $definitions = [];
        $definitionValues = [];

        foreach (DB::table('larasell_product_variant_dimensions')->orderBy('product_id')->orderBy('position')->get() as $dimension) {
            $attribute = DB::table('larasell_product_attributes')->find($dimension->product_attribute_id);
            $definitions[$attribute->id] ??= DB::table('larasell_option_definitions')->insertGetId([
                'slug' => $attribute->slug, 'name' => $attribute->name, 'display' => 'dropdown',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $optionId = DB::table('larasell_product_options')->insertGetId([
                'product_id' => $dimension->product_id,
                'option_definition_id' => $definitions[$attribute->id],
                'position' => $dimension->position,
            ]);

            $values = DB::table('larasell_product_attribute_values')
                ->join('larasell_product_product_attribute_value', 'larasell_product_attribute_values.id', '=', 'larasell_product_product_attribute_value.product_attribute_value_id')
                ->where('larasell_product_product_attribute_value.product_id', $dimension->product_id)
                ->where('product_attribute_id', $attribute->id)
                ->orderBy('position')->orderBy('larasell_product_attribute_values.id')
                ->select('larasell_product_attribute_values.*')->get();

            foreach ($values as $position => $value) {
                $definitionValues[$value->id] ??= DB::table('larasell_option_definition_values')->insertGetId([
                    'option_definition_id' => $definitions[$attribute->id], 'slug' => $value->slug, 'name' => $value->name,
                    'position' => $value->position ?? $position, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $productValueId = DB::table('larasell_product_option_values')->insertGetId([
                    'product_option_id' => $optionId, 'option_definition_value_id' => $definitionValues[$value->id], 'position' => $position,
                ]);

                $variantIds = DB::table('larasell_product_variant_product_attribute_value')
                    ->join('larasell_product_variants', 'larasell_product_variants.id', '=', 'product_variant_id')
                    ->where('larasell_product_variants.product_id', $dimension->product_id)
                    ->where('product_attribute_value_id', $value->id)
                    ->pluck('product_variant_id');

                foreach ($variantIds as $variantId) {
                    DB::table('larasell_product_variant_option_values')->insert([
                        'product_variant_id' => $variantId, 'product_option_id' => $optionId, 'product_option_value_id' => $productValueId,
                    ]);
                }
            }
        }

        foreach (DB::table('larasell_product_variant_option_values')->get()->groupBy('product_variant_id') as $variantId => $rows) {
            DB::table('larasell_product_variants')->where('id', $variantId)->update([
                'combination_key' => 'o:'.$rows->sortBy('product_option_id')->map(fn (object $row): string => "{$row->product_option_id}:{$row->product_option_value_id}")->implode('|'),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('larasell_product_variant_option_values')->delete();
        DB::table('larasell_product_option_values')->delete();
        DB::table('larasell_product_options')->delete();
    }
};
