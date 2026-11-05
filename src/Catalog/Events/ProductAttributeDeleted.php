<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A product attribute was deleted together with its values.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ProductAttributeDeleted
{
    use Dispatchable;

    /**
     * @param  list<int>  $valueIds
     */
    public function __construct(
        public int $attributeId,
        public array $valueIds,
    ) {}
}
