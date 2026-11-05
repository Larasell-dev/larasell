<?php

namespace Larasell\Larasell\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Storefront\StorefrontRoutes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The first middleware of every storefront route: puts back the path the client sent, so URLs built
 * from the request show it, and answers 404 for a route copy reached without its entry point.
 */
final class RestoreStorefrontPath
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Route $route */
        $route = $request->route();
        $original = $request->attributes->get(SetStorefrontContext::ORIGINAL_URI);
        $context = StorefrontContext::current();

        if (! is_string($original) || $context === null || $context->sales->locale !== StorefrontRoutes::localeOf($route)) {
            throw new NotFoundHttpException;
        }

        SetStorefrontContext::rewrite($request, $original);
        $request->attributes->remove(SetStorefrontContext::ORIGINAL_URI);

        return $next($request);
    }
}
