<?php

use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\SalesChannels\StorefrontRoutes;

if (! function_exists('storefront_route')) {
    /**
     * The absolute URL of a named storefront route, e.g. storefront_route('cart'), in the given context
     * or the current request's or job's. Its host and prefix come from the context's entry point.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    function storefront_route(string $name, array $parameters = [], ?SalesContext $context = null): string
    {
        $context ??= SalesContext::current()
            ?? throw new LogicException('storefront_route() needs a SalesContext outside a storefront request: pass one, e.g. SalesContext::for(\'default\', \'en\').');

        return StorefrontRoutes::url($name, $parameters, $context);
    }
}
