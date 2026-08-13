<?php

namespace Larasell\Larasell\Routing;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Models\Product;

class ProductDetailRoute
{
    public static function get(mixed $action, string $prefix): Route
    {
        RouteFacade::bind('product', function (string $value): Model {
            $productModel = app()->bound('config')
                ? config('larasell.models.product', Product::class)
                : Product::class;

            return $productModel::query()
                ->where('slug', $value)
                ->firstOrFail();
        });

        return RouteFacade::get(trim($prefix, '/').'/{product}', $action);
    }
}
