<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\AttributeId;

/**
 * A product attribute changed; $changed names its changed attributes.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ProductAttributeUpdated
{
    use Dispatchable;

    /**
     * @param  list<string>  $changed
     */
    public function __construct(
        public AttributeId $attributeId,
        public array $changed,
    ) {}
}
