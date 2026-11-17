<?php

namespace Larasell\Larasell\Catalog\Manufacturers\Actions;

use Larasell\Larasell\Catalog\Keep;
use Larasell\Larasell\Catalog\Manufacturers\PostalAddress;
use Larasell\Larasell\Catalog\Manufacturers\ResponsiblePerson;

/** What changes about a manufacturer; null keeps the name and address, Keep::Value the others, which null clears. */
final readonly class UpdateManufacturerPayload
{
    public function __construct(
        public ?string $name = null,
        public ?PostalAddress $address = null,
        public string|Keep|null $email = Keep::Value,
        public string|Keep|null $url = Keep::Value,
        public ResponsiblePerson|Keep|null $responsiblePerson = Keep::Value,
    ) {}

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return array_filter(
            ['name' => $this->name, 'address' => $this->address],
            fn (mixed $value): bool => $value !== null,
        ) + array_filter(
            ['email' => $this->email, 'url' => $this->url, 'responsible_person' => $this->responsiblePerson],
            fn (mixed $value): bool => $value !== Keep::Value,
        );
    }
}
