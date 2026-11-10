<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Customers\CustomerGroupId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Events\CustomerJoinedGroup;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Customers\Models\CustomerGroup;

/**
 * Puts a customer in a group. Does nothing when the customer is in it already.
 */
final readonly class AddCustomerToGroup
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerId $customerId, CustomerGroupId $groupId): void
    {
        $this->db->transaction(function () use ($customerId, $groupId): void {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId->value);
            $group = CustomerGroup::query()->findOrFail($groupId->value);

            if ($customer->groups()->whereKey($group->id)->exists()) {
                return;
            }

            $customer->groups()->attach($group->id);
            CustomerJoinedGroup::dispatch($customerId, $groupId);
        });
    }
}
