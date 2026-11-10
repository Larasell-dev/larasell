<?php

namespace Larasell\Larasell\Facades;

use Illuminate\Support\Facades\Facade;
use Larasell\Larasell\Catalog\ProductCapabilities as Registry;

/**
 * What product types can do, e.g. ProductCapabilities::register('download').
 *
 * @method static void register(string $capability)
 * @method static bool has(string $capability)
 * @method static list<string> all()
 *
 * @see Registry
 */
class ProductCapabilities extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Registry::class;
    }
}
