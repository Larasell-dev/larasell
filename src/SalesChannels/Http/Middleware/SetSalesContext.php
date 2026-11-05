<?php

namespace Larasell\Larasell\SalesChannels\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Larasell\Larasell\SalesChannels\SalesChannels;
use Larasell\Larasell\SalesChannels\StorefrontRoutes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Global middleware that runs before routing. When the request reaches an entry point and no route
 * outside the storefront matches it, it makes the entry point's context current, and maps the path
 * below the entry point onto its locale's route copy, e.g. https://shop.de/en/cart to /~en/cart.
 * RestoreStorefrontPath puts the path back once the route is matched.
 */
final readonly class SetSalesContext
{
    /** The request attribute holding the REQUEST_URI the client sent. */
    public const ORIGINAL_URI = 'larasell.original_request_uri';

    public function __construct(
        private SalesChannels $channels,
        private Router $router,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->channels->resolve($request);

        if ($context === null || $this->matchesOtherRoute($request)) {
            return $next($request);
        }

        $context->activate();
        App::setLocale($context->locale->value);

        $prefix = $context->entryPoint->prefix;
        $rest = ltrim(substr(ltrim($request->getPathInfo(), '/'), strlen($prefix)), '/');
        $query = $request->server->get('QUERY_STRING');

        $request->attributes->set(self::ORIGINAL_URI, $request->server->get('REQUEST_URI'));
        self::rewrite($request, $request->getBaseUrl().'/'.StorefrontRoutes::prefix($context->locale)
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

        return ! $route->isFallback && StorefrontRoutes::localeOf($route) === null;
    }
}
