<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Larasell\Larasell\Seo\Contracts\SeoResourceType;
use LogicException;

/** The resource types in larasell.seo.resources, and the paths reserved for them. */
final class SeoResources
{
    /** @return list<SeoResourceType<Model>> */
    public function all(): array
    {
        $types = [];

        foreach ((array) config('larasell.seo.resources', []) as $class) {
            $type = app($class);
            $key = $type->key();

            if (isset($types[$key])) {
                throw new LogicException('The SEO resource types ['.$types[$key]::class."] and [{$class}] both use the key [{$key}].");
            }

            $types[$key] = $type;
        }

        return array_values($types);
    }

    /** @return SeoResourceType<Model>|null */
    public function find(Model $resource): ?SeoResourceType
    {
        foreach ($this->all() as $type) {
            if ($resource instanceof ($type->model())) {
                return $type;
            }
        }

        return null;
    }

    /** @return SeoResourceType<Model>|null */
    public function findByKey(string $key): ?SeoResourceType
    {
        foreach ($this->all() as $type) {
            if ($type->key() === $key) {
                return $type;
            }
        }

        return null;
    }

    /** The page a URL row points at, e.g. its resource or replacement, or null when it no longer exists. */
    public function page(?string $key, ?int $id): ?Model
    {
        $type = $key === null || $id === null ? null : $this->findByKey($key);

        return $type?->model()::query()->find($id);
    }

    /** Whether pages of a type are instances of the class, e.g. a route parameter typed as Product. */
    public function handles(string $class): bool
    {
        foreach ($this->all() as $type) {
            if (is_a($type->model(), $class, true) || is_a($class, $type->model(), true)) {
                return true;
            }
        }

        return false;
    }

    /** @return SeoResourceType<Model> */
    public function for(Model $resource): SeoResourceType
    {
        return $this->find($resource)
            ?? throw new InvalidArgumentException('No SEO resource type is registered for ['.$resource::class.'].');
    }

    /**
     * Whether the path is reserved in the context: by larasell.seo.reserved_paths, by a static route of
     * Route::storefront() in that context ("cart", or its translation "warenkorb" in German), or by a
     * resource type other than this one.
     *
     * @param  SeoResourceType<Model>  $type
     */
    public function isReservedFor(string $path, SeoResourceType $type, SeoContext $context): bool
    {
        $prefixes = [...(array) config('larasell.seo.reserved_paths', []), ...$this->staticStorefrontPaths($context)];

        foreach ($this->all() as $other) {
            if ($other::class !== $type::class) {
                array_push($prefixes, ...$other->reservedPrefixes());
            }
        }

        foreach ($prefixes as $prefix) {
            $prefix = trim((string) $prefix, '/');

            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function staticStorefrontPaths(SeoContext $context): array
    {
        $paths = [];
        $key = SeoRoutes::entryPointKey($context);

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if ($route->getAction(SeoRoutes::ENTRY_POINT) !== $key) {
                continue;
            }

            $prefix = trim($route->getPrefix() ?? '', '/');
            $uri = trim($prefix === '' ? $route->uri() : substr($route->uri(), strlen($prefix)), '/');

            if ($uri !== '' && ! str_contains($uri, '{')) {
                $paths[] = $uri;
            }
        }

        return $paths;
    }
}
