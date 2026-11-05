<?php

namespace Larasell\Larasell\Seo\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Seo\Contracts\SeoResourceType;
use Larasell\Larasell\Seo\Contracts\Slugifier;
use Larasell\Larasell\Seo\SeoContext;
use Larasell\Larasell\Seo\SeoUrlManager;

/**
 * Categories live at their parent's current URL plus their own name: "schuhe/stiefel".
 * Building on the parent's stored URL means children follow its custom URL or suffix.
 *
 * @implements SeoResourceType<Category>
 */
class CategoryResourceType implements SeoResourceType
{
    public function __construct(private readonly Slugifier $slugs) {}

    public function key(): string
    {
        return 'category';
    }

    public function model(): string
    {
        return app(ModelRegistry::class)->category->class();
    }

    public function reservedPrefixes(): array
    {
        return [];
    }

    /** @param  Category  $resource */
    public function path(Model $resource, SeoContext $context): ?string
    {
        $slug = $this->slugs->slug($resource->name, $context->locale);

        if ($slug === null || $resource->parent_id === null) {
            return $slug;
        }

        $parent = app(SeoUrlManager::class)->canonicalPath($this->key(), $resource->parent_id, $context);

        return $parent === null ? null : $parent.'/'.$slug;
    }

    /** @param  Category  $resource */
    public function isAvailable(Model $resource, SeoContext $context): bool
    {
        return $resource->status === Visibility::Visible;
    }

    /**
     * @param  Category  $resource
     * @return Collection<int, Category>
     */
    public function dependents(Model $resource): Collection
    {
        return $resource->children()->get();
    }

    /** @param  Category  $resource */
    public function snapshot(Model $resource, SeoContext $context): array
    {
        return [
            'name' => $resource->name->get($context->locale->value),
            'parent_id' => $resource->parent_id,
        ];
    }
}
