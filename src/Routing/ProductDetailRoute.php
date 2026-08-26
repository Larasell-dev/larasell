<?php

namespace Larasell\Larasell\Routing;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Models\ModelRegistry;

class ProductDetailRoute
{
    public static function get(mixed $action, string $prefix): Route
    {
        RouteFacade::bind('product', function (string $value): Model {
            return app(ModelRegistry::class)->product->query()
                ->where('slug', $value)
                ->firstOrFail();
        });

        return RouteFacade::get(trim($prefix, '/').'/{product}', $action);
    }
}
