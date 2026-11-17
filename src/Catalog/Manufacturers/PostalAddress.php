<?php

namespace Larasell\Larasell\Catalog\Manufacturers;

use InvalidArgumentException;
use JsonSerializable;
use Larasell\Larasell\Enums\Country;

/**
 * Where a manufacturer or its responsible person can be reached by post. Unlike Address, it names
 * no person: the GPSR asks for the company's name and postal address.
 */
final readonly class PostalAddress implements JsonSerializable
{
    /** @var non-empty-list<string> */
    public array $street;

    /** @param  list<string>|string  $street  One or more street lines */
    public function __construct(
        array|string $street,
        public string $postcode,
        public string $city,
        public Country $country,
        public ?string $state = null,
    ) {
        $street = array_values(array_filter(is_array($street) ? $street : [$street], fn (string $line): bool => trim($line) !== ''));

        if ($street === [] || trim($city) === '') {
            throw new InvalidArgumentException('A postal address needs a street and a city.');
        }

        $this->street = $street;
    }

    /** @param  array<array-key, mixed>  $address */
    public static function fromArray(array $address): self
    {
        $street = $address['street'] ?? null;
        $country = is_string($address['country'] ?? null) ? Country::tryFrom($address['country']) : null;

        if ((! is_array($street) && ! is_string($street)) || ! is_string($address['postcode'] ?? null) || ! is_string($address['city'] ?? null) || $country === null) {
            throw new InvalidArgumentException('A postal address needs a street, postcode, city and country.');
        }

        $state = $address['state'] ?? null;

        return new self(
            is_array($street) ? array_values(array_map(strval(...), $street)) : $street,
            $address['postcode'],
            $address['city'],
            $country,
            is_string($state) ? $state : null,
        );
    }

    /** @return array{street: list<string>, postcode: string, city: string, state: string|null, country: string} */
    public function toArray(): array
    {
        return ['street' => $this->street, 'postcode' => $this->postcode, 'city' => $this->city, 'state' => $this->state, 'country' => $this->country->value];
    }

    /** @return array{street: list<string>, postcode: string, city: string, state: string|null, country: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
