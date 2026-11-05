<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Catalog\ImageId;

/**
 * A product image changed; $changed names its changed attributes.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ProductImageUpdated
{
    use Dispatchable;

    /**
     * @param  list<string>  $changed
     */
    public function __construct(
        public ImageId $imageId,
        public array $changed,
    ) {}
}
