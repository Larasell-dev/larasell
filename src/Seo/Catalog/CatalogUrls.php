<?php

namespace Larasell\Larasell\Seo\Catalog;

use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\CategoryCreated;
use Larasell\Larasell\Catalog\Events\CategoryDeleted;
use Larasell\Larasell\Catalog\Events\CategoryMoved;
use Larasell\Larasell\Catalog\Events\CategoryUpdated;
use Larasell\Larasell\Catalog\Events\ProductCreated;
use Larasell\Larasell\Catalog\Events\ProductDeleted;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Exceptions\CustomUrlRejected;
use Larasell\Larasell\Seo\Exceptions\InvalidSeoPath;
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
        $this->urls->refreshPage($this->resources->forClass(Product::class), $event->productId->value, $event->origin);
        $this->saveCustom(Product::class, $event->productId->value, $event->attachments, $event->origin);
    }

    public function productDeleted(ProductDeleted $event): void
    {
        $this->urls->markGoneById(
            $this->resources->forClass(Product::class),
            $event->productId->value,
            fn (SalesContext $context): array => ProductResourceType::snapshotOf($event->name, CategoryId::values($event->categoryIds), $context),
        );
    }

    /** Its subcategories follow in the same transaction. */
    public function categoryChanged(CategoryCreated|CategoryUpdated|CategoryMoved $event): void
    {
        $this->urls->refreshPage($this->resources->forClass(Category::class), $event->categoryId->value, $event->origin);

        if (! $event instanceof CategoryMoved) {
            $this->saveCustom(Category::class, $event->categoryId->value, $event->attachments, $event->origin);
        }
    }

    /** Its children are top-level categories now, so their paths lose the deleted category. */
    public function categoryDeleted(CategoryDeleted $event): void
    {
        $type = $this->resources->forClass(Category::class);

        $this->urls->markGoneById(
            $type,
            $event->categoryId->value,
            fn (SalesContext $context): array => CategoryResourceType::snapshotOf($event->name, $event->parentId?->value, $context),
        );

        foreach ($event->childIds as $childId) {
            $this->urls->refreshPage($type, $childId->value, $event->origin);
        }
    }

    /** @param  class-string<Model>  $class */
    private function saveCustom(string $class, int $id, Attachments $attachments, Origin $origin): void
    {
        $plan = $attachments->get(CustomUrls::class)->plan ?? [];

        if ($plan === []) {
            return;
        }

        $page = $class::query()->findOrFail($id);

        foreach ($plan as [$context, $path]) {
            try {
                $path === null
                    ? $this->urls->resetToAutomatic($page, $context, $origin)
                    : $this->urls->setCustom($page, $context, $path, $origin);
            } catch (InvalidSeoPath $exception) {
                throw CustomUrlRejected::in($context, $exception);
            }
        }
    }
}
