<?php

namespace Larasell\Larasell\Customers\Actions;

use Larasell\Larasell\Customers\CustomerGroupCode;
use Larasell\Larasell\Customers\CustomerGroupDescription;
use Larasell\Larasell\Translatable;

final readonly class CustomerGroupPayload
{
    public function __construct(
        /** Unique; how other modules and imports refer to the group, e.g. "wholesale". */
        public CustomerGroupCode $code,
        public Translatable $name,
        public ?CustomerGroupDescription $description = null,
    ) {}
}
