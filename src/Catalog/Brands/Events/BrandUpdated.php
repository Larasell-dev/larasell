<?php

namespace Larasell\Larasell\Catalog\Brands\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Brands\BrandId;

/**
 * A brand changed: which of its fields.
 * Dispatched inside the catalog action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class BrandUpdated
{
    use Dispatchable;

    /** @param  list<string>  $changed */
    public function __construct(
        public BrandId $brandId,
        public array $changed,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}
