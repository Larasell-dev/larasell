<?php

namespace Larasell\Larasell\Catalog\Manufacturers;

use InvalidArgumentException;
use JsonSerializable;

/**
 * The economic operator in the EU responsible for a manufacturer's products under the GPSR, needed
 * when the manufacturer itself is outside the EU.
 */
final readonly class ResponsiblePerson implements JsonSerializable
{
    public function __construct(
        public string $name,
        public PostalAddress $address,
        public ?string $email = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('The responsible person name is required.');
        }

        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('The responsible person email must be valid.');
        }
    }

    /** @param  array<array-key, mixed>  $person */
    public static function fromArray(array $person): self
    {
        if (! is_string($person['name'] ?? null) || ! is_array($person['address'] ?? null)) {
            throw new InvalidArgumentException('A responsible person needs a name and an address.');
        }

        $email = $person['email'] ?? null;

        return new self($person['name'], PostalAddress::fromArray($person['address']), is_string($email) ? $email : null);
    }

    /** @return array{name: string, address: array<string, mixed>, email: string|null} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'address' => $this->address->toArray(), 'email' => $this->email];
    }

    /** @return array{name: string, address: array<string, mixed>, email: string|null} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
