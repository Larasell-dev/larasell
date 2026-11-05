<?php

namespace Larasell\Larasell\Seo\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Contracts\SeoResourceType;
use Larasell\Larasell\Seo\Contracts\Slugifier;
use Larasell\Larasell\Translatable;

/** Products live at "p/{name}". The "p" prefix is reserved, so no other page can use it. *
 * @implements SeoResourceType<Product>
 */
class ProductResourceType implements SeoResourceType
{
    public const PREFIX = 'p';

    public function __construct(private readonly Slugifier $slugs) {}

    public function key(): string
    {
        return 'product';
    }

    public function model(): string
    {
        return Product::class;
    }

    public function reservedPrefixes(): array
    {
        return [static::PREFIX];
    }

    /** @param  Product  $resource */
    public function path(Model $resource, SalesContext $context): ?string
    {
        $slug = $this->slugs->slug($resource->name, $context->locale);

        return $slug === null ? null : static::PREFIX.'/'.$slug;
    }

    /** @param  Product  $resource */
    public function isAvailable(Model $resource, SalesContext $context): bool
    {
        return $resource->status === Visibility::Visible;
    }

    /** @return Collection<int, Product> */
    public function dependents(Model $resource): Collection
    {
        return new Collection;
    }

    /** @param  Product  $resource */
    public function snapshot(Model $resource, SalesContext $context): array
    {
        return self::snapshotOf($resource->name, array_values($resource->categories()->pluck('larasell_categories.id')->map(fn (mixed $id): int => (int) $id)->sort()->all()), $context);
    }

    /**
     * What a deleted product's URLs keep: its name and the categories it was in.
     *
     * @param  list<int>  $categoryIds
     * @return array{name: string, category_ids: list<int>}
     */
    public static function snapshotOf(Translatable $name, array $categoryIds, SalesContext $context): array
    {
        return ['name' => $name->get($context->locale->value), 'category_ids' => $categoryIds];
    }
}
