<?php

namespace Larasell\Larasell\Catalog\Brands\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Brands\BrandId;

/**
 * A brand was created.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class BrandCreated
{
    use Dispatchable;

    public function __construct(
        public BrandId $brandId,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}
