<?php

namespace Larasell\Larasell\Customers\Actions;

/** The person an individual customer is. Only the name is required. */
final readonly class PersonProfileData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $salutation = null,
    ) {}

    /** @return array<string, string|null> */
    public function toAttributes(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'salutation' => $this->salutation,
        ];
    }
}
