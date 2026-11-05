<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\VariantId;
use Larasell\Larasell\Translatable;

/**
 * A product was deleted together with its variants. The facts are what it was when it was deleted.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ProductDeleted
{
    use Dispatchable;

    /**
     * @param  list<CategoryId>  $categoryIds
     * @param  list<VariantId>  $variantIds
     */
    public function __construct(
        public ProductId $productId,
        public Translatable $name,
        public array $categoryIds,
        public array $variantIds,
    ) {}
}
