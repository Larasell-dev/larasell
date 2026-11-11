<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerAddressId;
use Larasell\Larasell\Customers\CustomerId;

/**
 * A customer address was deleted.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerAddressDeleted
{
    use Dispatchable;

    public function __construct(
        public CustomerAddressId $addressId,
        public CustomerId $customerId,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}
