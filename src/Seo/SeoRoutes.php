<?php

namespace Larasell\Larasell\Seo;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Reflector;
use Larasell\Larasell\Seo\Http\Controllers\SeoPageController;
use Larasell\Larasell\Storefront\StorefrontRoutes;
use LogicException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/** The Route::seo() macro: a catch-all for every SEO URL inside Route::storefront(). */
final class SeoRoutes
{
    public static function register(): void
    {
        /*
         * A catch-all for every SEO URL, mapping model classes to the controllers that show them:
         * Route::seo([Product::class => ShowProduct::class]). It is a fallback route, so Laravel tries
         * it after every other route, wherever it is registered. It only works inside Route::storefront(),
         * whose middleware resolves the page.
         */
        Router::macro('seo', function (array $controllers): Route {
            /** @var Router $this */
            $group = $this->hasGroupStack() ? $this->getGroupStack()[array_key_last($this->getGroupStack())] : [];

            if (! isset($group[StorefrontRoutes::LOCALE])) {
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
}
