<?php

namespace Larasell\Larasell;

use InvalidArgumentException;
use JsonSerializable;

final readonly class Address implements JsonSerializable
{
    /** @var array<int, string> */
    public array $street;

    /** @param array<int, string>|string $street */
    public function __construct(
        public string $country,
        public string $firstName,
        public string $lastName,
        array|string $street,
        public string $city,
        public string $postcode,
        public ?string $title = null,
        public ?string $state = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $company = null,
        public ?string $taxId = null,
    ) {
        $this->street = is_array($street) ? array_values($street) : [$street];

        foreach ([
            'country' => $country,
            'first name' => $firstName,
            'last name' => $lastName,
            'city' => $city,
            'postcode' => $postcode,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("The address {$field} is required.");
            }
        }

        if ($this->street === []) {
            throw new InvalidArgumentException('The address must contain one or more non-empty street lines.');
        }

        foreach ($this->street as $line) {
            if (! is_string($line) || trim($line) === '') {
                throw new InvalidArgumentException('The address must contain one or more non-empty street lines.');
            }
        }

        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('The address email must be valid.');
        }
    }

    /**
     * @param array{
     *     country: string,
     *     first_name: string,
     *     last_name: string,
     *     street: array<int, string>|string,
     *     city: string,
     *     postcode: string,
     *     title?: string|null,
     *     state?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     company?: string|null,
     *     tax_id?: string|null
     * } $address
     */
    public static function fromArray(array $address): self
    {
        foreach (['country', 'first_name', 'last_name', 'street', 'city', 'postcode'] as $field) {
            if (! array_key_exists($field, $address)) {
                throw new InvalidArgumentException("The address field [{$field}] is required.");
            }
        }

        return new self(
            country: $address['country'],
            firstName: $address['first_name'],
            lastName: $address['last_name'],
            street: $address['street'],
            city: $address['city'],
            postcode: $address['postcode'],
            title: $address['title'] ?? null,
            state: $address['state'] ?? null,
            email: $address['email'] ?? null,
            phone: $address['phone'] ?? null,
            company: $address['company'] ?? null,
            taxId: $address['tax_id'] ?? null,
        );
    }

    /** @return array<string, string|array<int, string>|null> */
    public function toArray(): array
    {
        return [
            'country' => $this->country,
            'title' => $this->title,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'tax_id' => $this->taxId,
        ];
    }

    /** @return array<string, string|array<int, string>|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
