<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Contracts\CustomerNumberGenerator;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\CustomerStatus;
use Larasell\Larasell\Customers\Enums\CustomerType;
use Larasell\Larasell\Customers\Events\CustomerCreated;
use Larasell\Larasell\Customers\Models\Customer;

/** Creates a customer with its profile and a number from the bound CustomerNumberGenerator. */
final readonly class CreateCustomer
{
    public function __construct(private ConnectionInterface $db, private CustomerNumberGenerator $numbers) {}

    public function handle(CreateCustomerPayload $payload, Origin $origin): Customer
    {
        return $this->db->transaction(function () use ($origin, $payload): Customer {
            $customer = Customer::query()->create([
                'customer_number' => $this->numbers->generate(),
                'type' => $payload->profile instanceof CompanyProfileData ? CustomerType::Company : CustomerType::Individual,
                'status' => CustomerStatus::Active,
                'source' => $payload->source,
                'preferred_locale' => $payload->preferredLocale,
                'preferred_currency' => $payload->preferredCurrency,
            ]);

            if ($payload->profile instanceof CompanyProfileData) {
                $customer->companyProfile()->create($payload->profile->toAttributes());
            } else {
                $customer->personProfile()->create($payload->profile->toAttributes());
            }

            CustomerCreated::dispatch(CustomerId::of($customer), $origin);

            return $customer->load(['personProfile', 'companyProfile']);
        });
    }
}
