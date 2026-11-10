<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Customers\CustomerGroupId;
use Larasell\Larasell\Customers\CustomerId;

/**
 * A customer was taken out of a group.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerLeftGroup
{
    use Dispatchable;

    public function __construct(
        public CustomerId $customerId,
        public CustomerGroupId $groupId,
    ) {}
}
