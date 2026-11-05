<?php

namespace Larasell\Larasell\Storefront;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Settings\LocaleSettings;
use LogicException;

/**
 * Route::storefront() and ->localized(). Storefront routes are registered once per language, below an
 * internal prefix such as "~de". SetStorefrontContext maps a request's entry point (host plus prefix) onto
 * that prefix before routing, so new entry points need no new routes and route:cache keeps working.
 */
final class StorefrontRoutes
{
    /** The route action key holding the locale a storefront route copy serves. */
    public const LOCALE = 'storefront_locale';

    /** The middleware group every storefront route runs; other modules may push middleware onto it. */
    public const MIDDLEWARE_GROUP = 'storefront';

    public static function register(): void
    {
        /* Registers the routes once per supported locale, named "{locale}.{name}", e.g. "de.cart". */
        Router::macro('storefront', function (Closure $routes): void {
            /** @var Router $router */
            $router = $this;

            foreach (app(LocaleSettings::class)->supported() as $locale) {
                $router->group([
                    'prefix' => StorefrontRoutes::prefix($locale),
                    'middleware' => ['web', StorefrontRoutes::MIDDLEWARE_GROUP],
                    'as' => $locale->value.'.',
                    StorefrontRoutes::LOCALE => $locale->value,
                ], $routes);
            }

            /* Laravel names unnamed routes after the group's name prefix ("de."); they stay unnamed. */
            foreach ($router->getRoutes()->getRoutes() as $route) {
                if (StorefrontRoutes::localeOf($route) !== null && str_ends_with((string) $route->getName(), '.')) {
                    $route->setAction(array_diff_key($route->getAction(), ['as' => true]));
                }
            }

            $router->getRoutes()->refreshNameLookups();
        });

        /*
         * Uses this route copy's translated path when lang/{locale}/routes.php has an entry for exactly
         * the path written in the routes file, e.g. 'cart/add' => 'warenkorb/hinzufuegen'. Without an
         * entry, the path stays as written. Translations must keep the route's parameters.
         */
        Route::macro('localized', function (): Route {
            /** @var Route $this */
            $locale = StorefrontRoutes::localeOf($this)
                ?? throw new LogicException('->localized() can only be used on routes inside Route::storefront().');

            $translated = StorefrontRoutes::translate(StorefrontRoutes::pathOf($this), $locale);

            if ($translated !== null) {
                $this->setUri(trim(StorefrontRoutes::prefix($locale).'/'.$translated, '/'));
            }

            return $this;
        });
    }

    /** The internal prefix of a locale's route copy. Visitors never see it. */
    public static function prefix(Locale $locale): string
    {
        return '~'.strtolower($locale->value);
    }

    /** The locale a storefront route copy serves, or null for routes outside Route::storefront(). */
    public static function localeOf(Route $route): ?Locale
    {
        $locale = $route->getAction(self::LOCALE);

        return is_string($locale) ? Locale::from($locale) : null;
    }

    /** A storefront route's path below its internal prefix, as visitors see it below an entry point. */
    public static function pathOf(Route $route): string
    {
        $locale = self::localeOf($route) ?? throw new LogicException('Only storefront routes have a storefront path.');

        return trim(substr(trim($route->uri(), '/'), strlen(self::prefix($locale))), '/');
    }

    /** The translation of exactly this path for the locale (or its language, e.g. "de" for "de-CH"), if any. */
    public static function translate(string $path, Locale $locale): ?string
    {
        foreach (Locale::lookupChain($locale->value) as $candidate) {
            $lines = app('translator')->get('routes', [], $candidate, false);
            $translated = is_array($lines) ? ($lines[$path] ?? null) : null;

            if (! is_string($translated)) {
                continue;
            }

            $translated = trim($translated, '/');

            if (self::parameters($translated) !== self::parameters($path)) {
                throw new InvalidArgumentException(
                    "The {$candidate} translation [{$translated}] of route [{$path}] must keep its parameters."
                );
            }

            return $translated;
        }

        return null;
    }

    /**
     * The absolute URL of a named storefront route in a context: its locale's route copy, below the context's entry point.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    public static function url(string $name, array $parameters, StorefrontContext $context): string
    {
        $relative = route($context->sales->locale->value.'.'.$name, $parameters, false);
        [$path, $query] = array_pad(explode('?', $relative, 2), 2, null);
        $path = substr(trim((string) $path, '/'), strlen(self::prefix($context->sales->locale)));

        return $context->url(rawurldecode(trim($path, '/'))).($query === null ? '' : '?'.$query);
    }

    /** @return list<string> */
    private static function parameters(string $path): array
    {
        preg_match_all('/\{(\w+)\??\}/', $path, $matches);
        $names = $matches[1];
        sort($names);

        return $names;
    }
}
