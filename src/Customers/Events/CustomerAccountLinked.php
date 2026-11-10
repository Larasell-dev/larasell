<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Customers\CustomerAccountId;
use Larasell\Larasell\Customers\CustomerId;

/**
 * One of the app's users now acts for a customer.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerAccountLinked
{
    use Dispatchable;

    public function __construct(
        public CustomerAccountId $accountId,
        public CustomerId $customerId,
    ) {}
}
