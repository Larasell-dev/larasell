<?php

namespace Larasell\Larasell\Seo\Catalog;

use Larasell\Larasell\Catalog\Events\CategoryCreated;
use Larasell\Larasell\Catalog\Events\CategoryDeleted;
use Larasell\Larasell\Catalog\Events\CategoryMoved;
use Larasell\Larasell\Catalog\Events\CategoryUpdated;
use Larasell\Larasell\Catalog\Events\ProductCreated;
use Larasell\Larasell\Catalog\Events\ProductDeleted;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Seo\SeoContext;
use Larasell\Larasell\Seo\SeoResources;
use Larasell\Larasell\Seo\SeoUrlManager;

/**
 * Keeps the URLs of products and categories in step with the catalog. A sync listener, so it writes
 * inside the catalog action's transaction: a new page has its URL at once, a deleted one answers 410
 * at once, and when a URL cannot be written, the catalog write fails with it.
 */
final readonly class CatalogUrls
{
    public function __construct(
        private SeoUrlManager $urls,
        private SeoResources $resources,
    ) {}

    public function productChanged(ProductCreated|ProductUpdated $event): void
    {
        $this->urls->refreshPage($this->resources->forClass(Product::class), $event->productId);
    }

    public function productDeleted(ProductDeleted $event): void
    {
        $this->urls->markGoneById(
            $this->resources->forClass(Product::class),
            $event->productId,
            fn (SeoContext $context): array => ProductResourceType::snapshotOf($event->name, $event->categoryIds, $context),
        );
    }

    /** Its subcategories follow in the same transaction. */
    public function categoryChanged(CategoryCreated|CategoryUpdated|CategoryMoved $event): void
    {
        $this->urls->refreshPage($this->resources->forClass(Category::class), $event->categoryId);
    }

    /** Its children are top-level categories now, so their paths lose the deleted category. */
    public function categoryDeleted(CategoryDeleted $event): void
    {
        $type = $this->resources->forClass(Category::class);

        $this->urls->markGoneById(
            $type,
            $event->categoryId,
            fn (SeoContext $context): array => CategoryResourceType::snapshotOf($event->name, $event->parentId, $context),
        );

        foreach ($event->childIds as $childId) {
            $this->urls->refreshPage($type, $childId);
        }
    }
}
