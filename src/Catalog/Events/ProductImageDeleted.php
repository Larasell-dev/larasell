<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A product image was deleted and removed from its products.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ProductImageDeleted
{
    use Dispatchable;

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(
        public int $imageId,
        public array $productIds,
    ) {}
}
