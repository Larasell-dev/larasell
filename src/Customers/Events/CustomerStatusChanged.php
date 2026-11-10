<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\CustomerStatus;

/**
 * A customer was blocked, archived or made active again.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerStatusChanged
{
    use Dispatchable;

    public function __construct(
        public CustomerId $customerId,
        public CustomerStatus $from,
        public CustomerStatus $to,
    ) {}
}
