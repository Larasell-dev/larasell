<?php

namespace Larasell\Larasell\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerContactId;
use Larasell\Larasell\Customers\CustomerId;

/**
 * A company customer got a new contact.
 * Dispatched inside the action's transaction: a sync listener writes in it and can undo it
 * by throwing; implement ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run after commit.
 */
final readonly class CustomerContactAdded
{
    use Dispatchable;

    public function __construct(
        public CustomerContactId $contactId,
        public CustomerId $customerId,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}
