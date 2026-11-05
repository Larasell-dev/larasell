<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\VariantId;

/**
 * A variant was added to a product.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ProductVariantCreated
{
    use Dispatchable;

    public function __construct(
        public VariantId $variantId,
        public ProductId $productId,
    ) {}
}
