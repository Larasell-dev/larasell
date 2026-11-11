<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\CustomerType;
use Larasell\Larasell\Customers\Events\CustomerUpdated;
use Larasell\Larasell\Customers\Exceptions\ProfileDoesNotMatchType;
use Larasell\Larasell\Customers\Models\Customer;

final readonly class UpdateCustomer
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerId $customerId, UpdateCustomerPayload $payload, Origin $origin): Customer
    {
        return $this->db->transaction(function () use ($origin, $customerId, $payload): Customer {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId->value);
            $isCompany = $payload->profile instanceof CompanyProfileData;

            if ($isCompany !== ($customer->type === CustomerType::Company)) {
                throw ProfileDoesNotMatchType::forCustomer($customerId, $customer->type);
            }

            $customer->update([
                'preferred_locale' => $payload->preferredLocale,
                'preferred_currency' => $payload->preferredCurrency,
            ]);

            $profile = $isCompany ? $customer->companyProfile() : $customer->personProfile();
            $profile->updateOrCreate([], $payload->profile->toAttributes());

            CustomerUpdated::dispatch($customerId, $origin);

            return $customer->load(['personProfile', 'companyProfile']);
        });
    }
}
