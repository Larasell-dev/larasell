<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Larasell\Larasell\Seo\Contracts\SeoResourceType;

/** The resource types in larasell.seo.resources, and the paths reserved for them. */
final class SeoResources
{
    /** @return list<SeoResourceType<Model>> */
    public function all(): array
    {
        return array_values(array_map(
            fn (string $class): SeoResourceType => app($class),
            (array) config('larasell.seo.resources', []),
        ));
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

    /** @return SeoResourceType<Model> */
    public function for(Model $resource): SeoResourceType
    {
        return $this->find($resource)
            ?? throw new InvalidArgumentException('No SEO resource type is registered for ['.$resource::class.'].');
    }

    /**
     * Whether the path is reserved for everyone (larasell.seo.reserved_paths) or for a type other than this one.
     *
     * @param  SeoResourceType<Model>  $type
     */
    public function isReservedFor(string $path, SeoResourceType $type): bool
    {
        $prefixes = (array) config('larasell.seo.reserved_paths', []);

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
}
