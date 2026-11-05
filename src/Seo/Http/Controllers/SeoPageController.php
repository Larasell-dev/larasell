<?php

namespace Larasell\Larasell\Seo\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Larasell\Larasell\Seo\Exceptions\SeoPageNotFound;

/**
 * The action of Route::seo(): hands the page ResolveStorefront bound to the controller mapped to
 * its model class, with the page injected by that class.
 */
final class SeoPageController
{
    public const PATH = 'seo_path';

    public const CONTROLLERS = 'seo_controllers';

    public function __invoke(Request $request, Container $container): mixed
    {
        /** @var Route $route */
        $route = $request->route();
        $page = $route->parameter(self::PATH);

        foreach ((array) ($route->defaults[self::CONTROLLERS] ?? []) as $model => $controller) {
            if (is_string($model) && $page instanceof $model) {
                return $container->call($controller, [$model => $page]);
            }
        }

        throw new SeoPageNotFound($request->path());
    }
}
