<?php

namespace Larasell\Larasell\Customers\Actions;

use Larasell\Larasell\Translatable;

final readonly class CustomerGroupPayload
{
    public function __construct(
        /** Unique; how other modules and imports refer to the group, e.g. "wholesale". */
        public string $code,
        public Translatable $name,
        public ?string $description = null,
    ) {}
}
