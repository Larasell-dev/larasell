<?php

namespace Larasell\Larasell\Catalog\Options;

use Illuminate\Support\Facades\DB;

/** How many variants, and of how many products, use an option's values. */
final class OptionUsage
{
    /**
     * @param  array<int>  $valueIds  Option definition value ids.
     * @return array{variants: int, products: int}
     */
    public static function ofValues(array $valueIds): array
    {
        $row = DB::table('larasell_product_variant_option_values as vov')
            ->join('larasell_product_option_values as pov', 'pov.id', '=', 'vov.product_option_value_id')
            ->join('larasell_product_options as po', 'po.id', '=', 'pov.product_option_id')
            ->whereIn('pov.option_definition_value_id', $valueIds)
            ->selectRaw('count(distinct vov.product_variant_id) as variants, count(distinct po.product_id) as products')
            ->first();

        return ['variants' => (int) ($row->variants ?? 0), 'products' => (int) ($row->products ?? 0)];
    }

    /**
     * Products stop offering the values; product options left without values go too.
     *
     * @param  array<int>  $valueIds
     */
    public static function withdraw(array $valueIds): void
    {
        $optionIds = DB::table('larasell_product_option_values')->whereIn('option_definition_value_id', $valueIds)->pluck('product_option_id')->unique()->all();
        DB::table('larasell_product_option_values')->whereIn('option_definition_value_id', $valueIds)->delete();
        DB::table('larasell_product_options')->whereIn('id', $optionIds)
            ->whereNotExists(fn ($query) => $query->from('larasell_product_option_values')->whereColumn('product_option_id', 'larasell_product_options.id'))
            ->delete();
    }
}
