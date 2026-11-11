<?php

namespace Larasell\Larasell\Customers\Actions;

use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\Enums\CustomerSource;
use Larasell\Larasell\Customers\Models\Customer;

/**
 * Creates a customer for someone who buys without an account. Every guest gets a new customer,
 * even with an email seen before; link an account later with LinkCustomerAccount.
 */
final readonly class CreateGuestCustomer
{
    public function __construct(private CreateCustomer $createCustomer) {}

    public function handle(PersonProfileData|CompanyProfileData $profile, Origin $origin): Customer
    {
        return $this->createCustomer->handle(new CreateCustomerPayload($profile, CustomerSource::Storefront), $origin);
    }
}
