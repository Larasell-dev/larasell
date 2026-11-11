<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerGroupId;

/**
 * A customer group was created.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerGroupCreated
{
    use Dispatchable;

    public function __construct(
        public CustomerGroupId $groupId,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}
