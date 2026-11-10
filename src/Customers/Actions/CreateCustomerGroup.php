<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Customers\CustomerGroupId;
use Larasell\Larasell\Customers\Events\CustomerGroupCreated;
use Larasell\Larasell\Customers\Exceptions\DuplicateCustomerGroupCode;
use Larasell\Larasell\Customers\Models\CustomerGroup;

final readonly class CreateCustomerGroup
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerGroupPayload $payload): CustomerGroup
    {
        return $this->db->transaction(function () use ($payload): CustomerGroup {
            if (CustomerGroup::query()->where('code', $payload->code->value())->exists()) {
                throw DuplicateCustomerGroupCode::code($payload->code->value());
            }

            $group = CustomerGroup::query()->create([
                'code' => $payload->code->value(),
                'name' => $payload->name,
                'description' => $payload->description?->value(),
            ]);

            CustomerGroupCreated::dispatch(CustomerGroupId::of($group));

            return $group;
        });
    }
}
