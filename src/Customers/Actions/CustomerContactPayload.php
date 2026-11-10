<?php

namespace Larasell\Larasell\Customers\Actions;

/** A person who acts for a company customer. */
final readonly class CustomerContactPayload
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $jobTitle = null,
    ) {}

    /** @return array<string, string|null> */
    public function toAttributes(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
        ];
    }
}
