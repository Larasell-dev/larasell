<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Audit\Origin;
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
        /** Who made the change and how it came in. */
        public Origin $origin,
        /** What the app passed along with the action for its own listeners. */
        public Attachments $attachments = new Attachments,
    ) {}
}
