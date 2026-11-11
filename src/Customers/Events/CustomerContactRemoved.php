<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerContactId;
use Larasell\Larasell\Customers\CustomerId;

/**
 * A contact was removed; accounts that pointed at it keep working without one.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerContactRemoved
{
    use Dispatchable;

    public function __construct(
        public CustomerContactId $contactId,
        public CustomerId $customerId,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}
