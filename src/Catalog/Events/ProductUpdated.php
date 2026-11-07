<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Catalog\ProductId;

/**
 * A product changed. $changed names its changed attributes, or "categories", "attribute_values" and "images" for its relations;
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
        /** What the app passed along with the action for its own listeners. */
        public Attachments $attachments = new Attachments,
    ) {}
}
