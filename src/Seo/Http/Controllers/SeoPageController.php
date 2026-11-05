<?php

namespace Larasell\Larasell\Seo\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Larasell\Larasell\Seo\Exceptions\SeoPageNotFound;

/**
 * The action of Route::seo(): hands the page ResolveStorefront bound to the controller mapped to
 * its model class, with the page passed to the parameter Route::seo() found for it.
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

        foreach ((array) ($route->defaults[self::CONTROLLERS] ?? []) as $model => [$controller, $parameter]) {
            if (is_string($model) && $page instanceof $model) {
                return $container->call($controller, $parameter === null ? [] : [$parameter => $page]);
            }
        }

        // Route::seo() maps every resource type it was given; a page of another type has no controller here.
        throw new SeoPageNotFound($request->path());
    }
}
