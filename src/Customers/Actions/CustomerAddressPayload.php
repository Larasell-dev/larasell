<?php

namespace Larasell\Larasell\Customers\Actions;

use Larasell\Larasell\Enums\Country;
use Larasell\Larasell\PhoneNumber;

/** A customer address. Which address is a default is set with SetDefaultCustomerAddress. */
final readonly class CustomerAddressPayload
{
    /** @param list<string> $street One or more street lines */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public array $street,
        public string $postcode,
        public string $city,
        public Country $countryCode,
        public ?string $company = null,
        public ?string $region = null,
        public ?PhoneNumber $phone = null,
    ) {}

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'street' => $this->street,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'country_code' => $this->countryCode,
            'company' => $this->company,
            'region' => $this->region,
            'phone' => $this->phone,
        ];
    }
}
