<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Customers\CustomerAddressId;
use Larasell\Larasell\Customers\CustomerId;

/**
 * A customer got a new address.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerAddressAdded
{
    use Dispatchable;

    public function __construct(
        public CustomerAddressId $addressId,
        public CustomerId $customerId,
    ) {}
}
