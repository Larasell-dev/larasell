<?php

namespace Larasell\Larasell\Facades;

use BackedEnum;
use Closure;
use Illuminate\Support\Facades\Facade;
use Larasell\Larasell\Catalog\ProductType;
use Larasell\Larasell\Catalog\ProductTypes as Registry;

/**
 * The product types the app sells, e.g. ProductTypes::register(ProductType::make('gift_card')).
 *
 * @method static void register(ProductType $type)
 * @method static void extend(string|BackedEnum $type, Closure $extension)
 * @method static ProductType get(string|BackedEnum $type)
 * @method static bool has(string|BackedEnum $type)
 * @method static list<ProductType> all()
 * @method static list<string> keysWith(string $capability)
 *
 * @see Registry
 */
class ProductTypes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Registry::class;
    }
}
