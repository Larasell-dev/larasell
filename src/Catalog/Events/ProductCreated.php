<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\ProductId;

/**
 * A product was created, with its default variant.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ProductCreated
{
    use Dispatchable;

    public function __construct(
        public ProductId $productId,
    ) {}
}
