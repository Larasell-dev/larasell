<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Customers\CustomerAddressId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Events\CustomerAddressDeleted;
use Larasell\Larasell\Customers\Models\CustomerAddress;

/** Deletes an address. When it was a default, the customer has no default for that use until one is set. */
final readonly class DeleteCustomerAddress
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerAddressId $addressId): void
    {
        $this->db->transaction(function () use ($addressId): void {
            $address = CustomerAddress::query()->findOrFail($addressId->value);
            $address->delete();

            CustomerAddressDeleted::dispatch($addressId, new CustomerId($address->customer_id));
        });
    }
}
