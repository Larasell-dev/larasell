<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Customers\CustomerGroupId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Events\CustomerLeftGroup;
use Larasell\Larasell\Customers\Models\Customer;

final readonly class RemoveCustomerFromGroup
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerId $customerId, CustomerGroupId $groupId): void
    {
        $this->db->transaction(function () use ($customerId, $groupId): void {
            $customer = Customer::query()->findOrFail($customerId->value);

            if ($customer->groups()->detach($groupId->value) > 0) {
                CustomerLeftGroup::dispatch($customerId, $groupId);
            }
        });
    }
}
