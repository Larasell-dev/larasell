<?php

namespace Larasell\Larasell\Customers\Actions;

use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;

/** A customer's new profile and preferences. The profile must be of the customer's type. */
final readonly class UpdateCustomerPayload
{
    public function __construct(
        public PersonProfileData|CompanyProfileData $profile,
        public ?Locale $preferredLocale = null,
        public ?Currency $preferredCurrency = null,
    ) {}
}
