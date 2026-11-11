<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerGroupId;
use Larasell\Larasell\Customers\CustomerId;

/**
 * A customer was put in a group.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerJoinedGroup
{
    use Dispatchable;

    public function __construct(
        public CustomerId $customerId,
        public CustomerGroupId $groupId,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}
