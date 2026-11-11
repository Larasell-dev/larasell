<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerAddressId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\AddressUse;
use Larasell\Larasell\Customers\Events\CustomerAddressUpdated;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Customers\Models\CustomerAddress;

/** Makes an address its customer's default for billing or shipping, in place of the previous one. */
final readonly class SetDefaultCustomerAddress
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerAddressId $addressId, AddressUse $use, Origin $origin): CustomerAddress
    {
        return $this->db->transaction(function () use ($origin, $addressId, $use): CustomerAddress {
            $address = CustomerAddress::query()->findOrFail($addressId->value);
            Customer::query()->lockForUpdate()->findOrFail($address->customer_id);
            $column = match ($use) {
                AddressUse::Billing => 'is_default_billing',
                AddressUse::Shipping => 'is_default_shipping',
            };

            CustomerAddress::query()->where('customer_id', $address->customer_id)->update([$column => false]);
            $address->update([$column => true]);

            CustomerAddressUpdated::dispatch($addressId, new CustomerId($address->customer_id), $origin);

            return $address;
        });
    }
}
