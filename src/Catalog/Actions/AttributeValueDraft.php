<?php

namespace Larasell\Larasell\Catalog\Actions;

use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Translatable;

/** One of the values SaveProductAttribute leaves an attribute with. */
final readonly class AttributeValueDraft
{
    public function __construct(
        /** The value it changes, or null for a new one. */
        public ?AttributeValueId $id,
        /** All of its translations, e.g. `$value->name->with('de', 'Baumwolle')`. */
        public Translatable $name,
        public string $slug,
    ) {}
}
