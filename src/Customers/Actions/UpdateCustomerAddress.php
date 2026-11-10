<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Customers\CustomerAddressId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Events\CustomerAddressUpdated;
use Larasell\Larasell\Customers\Models\CustomerAddress;

final readonly class UpdateCustomerAddress
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerAddressId $addressId, CustomerAddressPayload $payload): CustomerAddress
    {
        return $this->db->transaction(function () use ($addressId, $payload): CustomerAddress {
            $address = CustomerAddress::query()->findOrFail($addressId->value);
            $address->update($payload->toAttributes());

            CustomerAddressUpdated::dispatch($addressId, new CustomerId($address->customer_id));

            return $address;
        });
    }
}
