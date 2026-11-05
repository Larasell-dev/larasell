<?php

namespace Larasell\Larasell\Seo;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Reflector;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Seo\Contracts\SeoContextResolver;
use Larasell\Larasell\Seo\Http\Controllers\SeoPageController;
use Larasell\Larasell\Seo\Http\Middleware\ResolveStorefront;
use LogicException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/** The Route::storefront() and Route::seo() macros, and ->localized() on storefront routes. */
final class SeoRoutes
{
    /** The route action key holding the entry point a storefront route was registered for. */
    public const ENTRY_POINT = 'storefront_entry_point';

    public static function register(): void
    {
        /*
         * Registers the routes once per entry point, bound to its host and prefix, so every copy knows
         * its channel and locale. Longer prefixes come first, so /en/... never reaches the copy without
         * a prefix. With several entry points, route names get a "{channel}.{locale}." prefix.
         */
        Router::macro('storefront', function (Closure $routes): void {
            /** @var Router $this */
            $contexts = app(SeoContextResolver::class)->contexts();
            usort($contexts, fn (SeoContext $a, SeoContext $b): int => strlen($b->prefix) <=> strlen($a->prefix));

            foreach ($contexts as $context) {
                $this->group(array_filter([
                    'domain' => $context->host,
                    'prefix' => $context->prefix,
                    'middleware' => ['web', ResolveStorefront::class],
                    'as' => count($contexts) > 1 ? $context->channel.'.'.$context->locale->value.'.' : null,
                    SeoRoutes::ENTRY_POINT => SeoRoutes::entryPointKey($context),
                ], fn (mixed $value): bool => $value !== null && $value !== ''), $routes);
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

            if (! isset($group[SeoRoutes::ENTRY_POINT])) {
                throw new LogicException('Route::seo() must be registered inside Route::storefront().');
            }

            $pages = [];

            foreach ($controllers as $model => $controller) {
                $pages[$model] = [$controller, SeoRoutes::pageParameter($model, $controller)];
            }

            return $this->get('{'.SeoPageController::PATH.'}', SeoPageController::class)
                ->where(SeoPageController::PATH, '.*')
                ->defaults(SeoPageController::CONTROLLERS, $pages)
                ->fallback();
        });

        /*
         * Uses this route copy's translated path when lang/{locale}/routes.php has an entry for exactly
         * the path written in the routes file, e.g. 'cart/add' => 'warenkorb/hinzufuegen'. Without an
         * entry, the path stays as written. Translations must keep the route's parameters.
         */
        Route::macro('localized', function (): Route {
            /** @var Route $this */
            $context = SeoRoutes::contextOf($this)
                ?? throw new LogicException('->localized() can only be used on routes inside Route::storefront().');

            $prefix = trim($this->getPrefix() ?? '', '/');
            $path = trim($prefix === '' ? $this->uri() : substr($this->uri(), strlen($prefix)), '/');
            $translated = SeoRoutes::translate($path, $context->locale);

            if ($translated !== null) {
                $this->setUri(trim($prefix.'/'.$translated, '/') ?: '/');
            }

            return $this;
        });
    }

    /**
     * The name of the controller's parameter that receives pages of the model class, or null when it
     * takes none (e.g. it reads the page from a form request). Checked when the route is registered,
     * so a wrong map fails at boot instead of handing a controller an empty model.
     */
    public static function pageParameter(mixed $model, mixed $controller): ?string
    {
        if (! is_string($model) || ! is_subclass_of($model, Model::class) || ! app(SeoResources::class)->handles($model)) {
            throw new LogicException('Route::seo() maps ['.(is_string($model) ? $model : get_debug_type($model)).'], which no SEO resource type handles.');
        }

        foreach (self::reflect($controller)->getParameters() as $parameter) {
            $class = Reflector::getParameterClassName($parameter);

            if ($class === null || ! is_a($class, Model::class, true)) {
                continue;
            }

            if (! is_a($model, $class, true)) {
                throw new LogicException("The Route::seo() controller for [{$model}] cannot receive it: its parameter \${$parameter->getName()} takes [{$class}].");
            }

            return $parameter->getName();
        }

        return null;
    }

    private static function reflect(mixed $controller): ReflectionFunctionAbstract
    {
        if ($controller instanceof Closure) {
            return new ReflectionFunction($controller);
        }

        if (is_string($controller) && str_contains($controller, '@')) {
            $controller = explode('@', $controller, 2);
        }

        return match (true) {
            is_string($controller) && class_exists($controller) => new ReflectionMethod($controller, '__invoke'),
            is_array($controller) && isset($controller[0], $controller[1]) => new ReflectionMethod($controller[0], $controller[1]),
            default => throw new LogicException('A Route::seo() controller must be a closure, an invokable class, or [class, method].'),
        };
    }

    public static function entryPointKey(SeoContext $context): string
    {
        return $context->host.'/'.$context->prefix;
    }

    /** The context a storefront route was registered for, or null for routes outside Route::storefront(). */
    public static function contextOf(Route $route): ?SeoContext
    {
        $key = $route->getAction(self::ENTRY_POINT);

        foreach (is_string($key) ? app(SeoContextResolver::class)->contexts() : [] as $context) {
            if (self::entryPointKey($context) === $key) {
                return $context;
            }
        }

        return null;
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

    /** @return list<string> */
    private static function parameters(string $path): array
    {
        preg_match_all('/\{(\w+)\??\}/', $path, $matches);
        $names = $matches[1];
        sort($names);

        return $names;
    }
}
