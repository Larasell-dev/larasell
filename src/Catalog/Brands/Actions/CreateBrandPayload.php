<?php

namespace Larasell\Larasell\Catalog\Brands\Actions;

use Larasell\Larasell\Translatable;

/** A new brand. */
final readonly class CreateBrandPayload
{
    public function __construct(
        /** All of its translations, e.g. `new Translatable(['en' => 'Acme'])`. */
        public Translatable $name,
        public string $slug,
        public ?Translatable $description = null,
    ) {}
}
