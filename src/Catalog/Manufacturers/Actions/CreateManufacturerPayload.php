<?php

namespace Larasell\Larasell\Catalog\Manufacturers\Actions;

use Larasell\Larasell\Catalog\Manufacturers\PostalAddress;
use Larasell\Larasell\Catalog\Manufacturers\ResponsiblePerson;

/** A new manufacturer. */
final readonly class CreateManufacturerPayload
{
    public function __construct(
        /** Its legal name, the same in every language. */
        public string $name,
        public PostalAddress $address,
        public ?string $email = null,
        public ?string $url = null,
        /** Needed under the GPSR when the manufacturer is outside the EU. */
        public ?ResponsiblePerson $responsiblePerson = null,
    ) {}

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'email' => $this->email,
            'url' => $this->url,
            'responsible_person' => $this->responsiblePerson,
        ];
    }
}
