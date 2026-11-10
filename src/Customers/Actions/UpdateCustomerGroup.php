<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Customers\CustomerGroupId;
use Larasell\Larasell\Customers\Events\CustomerGroupUpdated;
use Larasell\Larasell\Customers\Exceptions\DuplicateCustomerGroupCode;
use Larasell\Larasell\Customers\Models\CustomerGroup;

final readonly class UpdateCustomerGroup
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerGroupId $groupId, CustomerGroupPayload $payload): CustomerGroup
    {
        return $this->db->transaction(function () use ($groupId, $payload): CustomerGroup {
            $group = CustomerGroup::query()->findOrFail($groupId->value);

            if (CustomerGroup::query()->where('code', $payload->code->value())->whereKeyNot($group->id)->exists()) {
                throw DuplicateCustomerGroupCode::code($payload->code->value());
            }

            $group->update([
                'code' => $payload->code->value(),
                'name' => $payload->name,
                'description' => $payload->description?->value(),
            ]);

            CustomerGroupUpdated::dispatch($groupId);

            return $group;
        });
    }
}
