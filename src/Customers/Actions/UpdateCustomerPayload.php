<?php

namespace Larasell\Larasell\Customers\Actions;

use Larasell\Larasell\Enums\Currency;

/** A customer's new profile and preferences. The profile must be of the customer's type. */
final readonly class UpdateCustomerPayload
{
    public function __construct(
        public PersonProfileData|CompanyProfileData $profile,
        public ?string $preferredLocale = null,
        public ?Currency $preferredCurrency = null,
    ) {}
}
