<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\CustomerStatus;
use Larasell\Larasell\Customers\Events\CustomerStatusChanged;
use Larasell\Larasell\Customers\Models\Customer;

/** Blocks, archives or reactivates a customer. Setting the status it already has does nothing. */
final readonly class ChangeCustomerStatus
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerId $customerId, CustomerStatus $status): Customer
    {
        return $this->db->transaction(function () use ($customerId, $status): Customer {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId->value);
            $from = $customer->status;

            if ($from === $status) {
                return $customer;
            }

            $customer->update(['status' => $status]);
            CustomerStatusChanged::dispatch($customerId, $from, $status);

            return $customer;
        });
    }
}
