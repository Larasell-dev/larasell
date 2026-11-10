<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Customers\CustomerId;

/**
 * A customer's profile or preferences changed.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerUpdated
{
    use Dispatchable;

    public function __construct(
        public CustomerId $customerId,
    ) {}
}
