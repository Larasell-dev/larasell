<?php

namespace Larasell\Larasell\Customers\Actions;

use Larasell\Larasell\Customers\Enums\CustomerSource;
use Larasell\Larasell\Enums\Currency;

/** A new customer: the profile decides whether it is an individual or a company. */
final readonly class CreateCustomerPayload
{
    public function __construct(
        public PersonProfileData|CompanyProfileData $profile,
        public CustomerSource $source,
        public ?string $preferredLocale = null,
        public ?Currency $preferredCurrency = null,
    ) {}
}
