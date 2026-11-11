<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\ProductId;

/**
 * A product changed. $changed names its changed attributes, or "categories" and "attribute_values" for its relations;
 * empty when only attachments were passed along.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ProductUpdated
{
    use Dispatchable;

    /**
     * @param  list<string>  $changed
     */
    public function __construct(
        public ProductId $productId,
        public array $changed,
        /** Who made the change and how it came in. */
        public Origin $origin,
        /** What the app passed along with the action for its own listeners. */
        public Attachments $attachments = new Attachments,
    ) {}
}
