<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Customers\CustomerAddressId;
use Larasell\Larasell\Customers\CustomerId;

/**
 * A customer's address, or which address is a default, changed.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerAddressUpdated
{
    use Dispatchable;

    public function __construct(
        public CustomerAddressId $addressId,
        public CustomerId $customerId,
    ) {}
}
