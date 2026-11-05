<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\AttributeValueId;

/**
 * A value was added to a product attribute.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ProductAttributeValueCreated
{
    use Dispatchable;

    public function __construct(
        public AttributeValueId $valueId,
        public AttributeId $attributeId,
    ) {}
}
