<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Customers\CustomerAddressId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Events\CustomerAddressAdded;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Customers\Models\CustomerAddress;

/** Adds an address. A customer's first address becomes its default for billing and shipping. */
final readonly class AddCustomerAddress
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerId $customerId, CustomerAddressPayload $payload): CustomerAddress
    {
        return $this->db->transaction(function () use ($customerId, $payload): CustomerAddress {
            // Locks the customer so two first addresses can't both become the default.
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId->value);
            $first = ! $customer->addresses()->exists();

            $address = $customer->addresses()->create([
                ...$payload->toAttributes(),
                'is_default_billing' => $first,
                'is_default_shipping' => $first,
            ]);

            CustomerAddressAdded::dispatch(CustomerAddressId::of($address), $customerId);

            return $address;
        });
    }
}
