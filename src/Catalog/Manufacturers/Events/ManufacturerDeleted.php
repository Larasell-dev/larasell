<?php

namespace Larasell\Larasell\Catalog\Manufacturers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Manufacturers\ManufacturerId;

/**
 * A manufacturer was deleted; no product used it.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class ManufacturerDeleted
{
    use Dispatchable;

    public function __construct(
        public ManufacturerId $manufacturerId,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}
