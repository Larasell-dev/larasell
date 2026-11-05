<?php

namespace Larasell\Larasell\Seo;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Larasell\Larasell\Seo\Contracts\SeoContextResolver;
use Larasell\Larasell\Seo\Http\Controllers\SeoPageController;
use Larasell\Larasell\Seo\Http\Middleware\ResolveStorefront;
use LogicException;

/** The Route::storefront() and Route::seo() macros. */
final class SeoRoutes
{
    public static function register(): void
    {
        /*
         * Registers the routes for every entry point. Prefixed entry points share one group with a
         * {storefront} prefix; entry points without a prefix get a second copy of the routes, with
         * route names prefixed "unprefixed." when both exist.
         */
        Router::macro('storefront', function (Closure $routes): void {
            /** @var Router $this */
            $prefixes = array_unique(array_map(
                fn (SeoContext $context): string => $context->prefix,
                app(SeoContextResolver::class)->contexts(),
            ));
            $prefixed = array_values(array_filter($prefixes, fn (string $prefix): bool => $prefix !== ''));
            $middleware = ['web', ResolveStorefront::class];

            if ($prefixed !== []) {
                $this->middleware($middleware)
                    ->prefix('{'.ResolveStorefront::PREFIX.'}')
                    ->where([ResolveStorefront::PREFIX => implode('|', array_map(preg_quote(...), $prefixed))])
                    ->group($routes);
            }

            if (in_array('', $prefixes, true)) {
                $group = $this->middleware($middleware);
                ($prefixed === [] ? $group : $group->name('unprefixed.'))->group($routes);
            }
        });

        /*
         * A catch-all for every SEO URL, mapping model classes to the controllers that show them:
         * Route::seo([Product::class => ShowProduct::class]). It is a fallback route, so Laravel tries
         * it after every other route, wherever it is registered. It only works inside Route::storefront(),
         * whose middleware resolves the page.
         */
        Router::macro('seo', function (array $controllers): Route {
            /** @var Router $this */
            $group = $this->hasGroupStack() ? $this->getGroupStack()[array_key_last($this->getGroupStack())] : [];

            if (! in_array(ResolveStorefront::class, (array) ($group['middleware'] ?? []), true)) {
                throw new LogicException('Route::seo() must be registered inside Route::storefront().');
            }

            return $this->get('{'.SeoPageController::PATH.'}', SeoPageController::class)
                ->where(SeoPageController::PATH, '.*')
                ->defaults(SeoPageController::CONTROLLERS, $controllers)
                ->fallback();
        });
    }
}
