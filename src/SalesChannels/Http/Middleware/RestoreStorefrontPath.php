<?php

namespace Larasell\Larasell\SalesChannels\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\SalesChannels\StorefrontRoutes;
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
        $original = $request->attributes->get(SetSalesContext::ORIGINAL_URI);
        $context = SalesContext::current();

        if (! is_string($original) || $context === null || $context->locale !== StorefrontRoutes::localeOf($route)) {
            throw new NotFoundHttpException;
        }

        SetSalesContext::rewrite($request, $original);
        $request->attributes->remove(SetSalesContext::ORIGINAL_URI);

        return $next($request);
    }
}
