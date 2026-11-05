<?php

namespace Larasell\Larasell\Seo\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Seo\Contracts\SeoResourceType;
use Larasell\Larasell\Seo\Contracts\Slugifier;
use Larasell\Larasell\Seo\SeoContext;

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
    public function path(Model $resource, SeoContext $context): ?string
    {
        $slug = $this->slugs->slug($resource->name, $context->locale);

        return $slug === null ? null : static::PREFIX.'/'.$slug;
    }

    /** @param  Product  $resource */
    public function isAvailable(Model $resource, SeoContext $context): bool
    {
        return $resource->status === Visibility::Visible;
    }

    /** @return Collection<int, Product> */
    public function dependents(Model $resource): Collection
    {
        return new Collection;
    }

    /** @param  Product  $resource */
    public function snapshot(Model $resource, SeoContext $context): array
    {
        return [
            'name' => $resource->name->get($context->locale->value),
            'category_ids' => $resource->categories()->pluck($resource->categories()->getRelated()->getQualifiedKeyName())->all(),
        ];
    }
}
