<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\AttributeValueId;

/**
 * A product attribute was deleted together with its values.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ProductAttributeDeleted
{
    use Dispatchable;

    /**
     * @param  list<AttributeValueId>  $valueIds
     */
    public function __construct(
        public AttributeId $attributeId,
        public array $valueIds,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}
