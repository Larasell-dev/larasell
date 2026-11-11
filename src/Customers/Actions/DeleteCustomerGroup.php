<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerGroupId;
use Larasell\Larasell\Customers\Events\CustomerGroupDeleted;
use Larasell\Larasell\Customers\Exceptions\CustomerGroupInUse;
use Larasell\Larasell\Customers\Models\CustomerGroup;

/** Deletes a group that has no customers in it. */
final readonly class DeleteCustomerGroup
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerGroupId $groupId, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $groupId): void {
            $group = CustomerGroup::query()->lockForUpdate()->findOrFail($groupId->value);
            $members = $group->customers()->count();

            if ($members > 0) {
                throw CustomerGroupInUse::hasMembers($groupId, $members);
            }

            $group->delete();
            CustomerGroupDeleted::dispatch($groupId, $origin);
        });
    }
}
