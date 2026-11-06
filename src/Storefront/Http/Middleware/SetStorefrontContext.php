<?php

namespace Larasell\Larasell\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Larasell\Larasell\Storefront\StorefrontRoutes;
use Larasell\Larasell\Storefront\Storefronts;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Global middleware that runs before routing. When the request reaches an entry point and no route
 * outside the storefront matches it, it makes the entry point's context current, and maps the path
 * below the entry point onto its locale's route copy, e.g. https://shop.de/en/cart to /~en/cart.
 * RestoreStorefrontPath puts the path back once the route is matched.
 */
final readonly class SetStorefrontContext
{
    /** The request attribute holding the REQUEST_URI the client sent. */
    public const ORIGINAL_URI = 'larasell.original_request_uri';

    public function __construct(
        private Storefronts $storefronts,
        private Router $router,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->storefronts->resolve($request);

        if ($context === null || $this->matchesOtherRoute($request)) {
            return $next($request);
        }

        $context->activate();
        App::setLocale($context->sales->locale->value);

        $prefix = $context->entryPoint->prefix;
        $rest = ltrim(substr(ltrim($request->getPathInfo(), '/'), strlen($prefix)), '/');
        $query = $request->server->get('QUERY_STRING');

        $request->attributes->set(self::ORIGINAL_URI, $request->server->get('REQUEST_URI'));
        self::rewrite($request, $request->getBaseUrl().'/'.StorefrontRoutes::prefix($context->sales->locale)
            .($rest === '' ? '' : '/'.$rest).(is_string($query) && $query !== '' ? '?'.$query : ''));

        return $next($request);
    }

    /** Points the request at another REQUEST_URI, so routing and path helpers see that path. */
    public static function rewrite(Request $request, string $uri): void
    {
        $server = $request->server->all();
        $server['REQUEST_URI'] = $uri;

        $request->initialize(
            $request->query->all(), $request->request->all(), $request->attributes->all(),
            $request->cookies->all(), $request->files->all(), $server, $request->getContent(),
        );
    }

    private function matchesOtherRoute(Request $request): bool
    {
        try {
            $route = $this->router->getRoutes()->match($request);
        } catch (HttpException) {
            return false;
        }

        if (StorefrontRoutes::localeOf($route) !== null) {
            return false;
        }

        // A catch-all at the root, such as the app's Route::fallback(), yields to the storefront.
        // One below a fixed path, such as an admin panel's admin/{any}, owns that path.
        return ! $route->isFallback || ! str_starts_with($route->uri(), '{');
    }
}
