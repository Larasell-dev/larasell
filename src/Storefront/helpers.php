<?php

use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Storefront\StorefrontRoutes;

if (! function_exists('storefront_route')) {
    /**
     * The absolute URL of a named storefront route, e.g. storefront_route('cart'), in the given
     * storefront context or the current request's or job's. Its host and prefix come from the entry point.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    function storefront_route(string $name, array $parameters = [], ?StorefrontContext $context = null): string
    {
        $context ??= StorefrontContext::current()
            ?? throw new LogicException('storefront_route() needs a StorefrontContext outside a storefront request: pass one, e.g. StorefrontContext::for(\'default\', \'en\').');

        return StorefrontRoutes::url($name, $parameters, $context);
    }
}
