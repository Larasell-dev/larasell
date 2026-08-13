<?php

namespace Larasell\Larasell\Routing;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

class ProductListingRoute
{
    public static function get(mixed $action, string $prefix): Route
    {
        return RouteFacade::get(trim($prefix, '/').'/{category?}', $action)
            ->where('category', '.*');
    }
}
