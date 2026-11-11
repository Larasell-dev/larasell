<?php

namespace Larasell\Larasell\Catalog\Actions;

use Larasell\Larasell\Catalog\AttributeCardinality;
use Larasell\Larasell\Catalog\AttributeScope;
use Larasell\Larasell\Enums\ProductAttributeType;
use Larasell\Larasell\Translatable;

/** An attribute as SaveProductAttribute leaves it, values included. */
final readonly class SaveProductAttributePayload
{
    /**
     * @param  list<AttributeValueDraft>  $values  All of its values, in the order products show them.
     */
    public function __construct(
        /** All of its translations, e.g. `$attribute->name->with('de', 'Material')`. */
        public Translatable $name,
        public string $slug,
        /** Set when creating; an attribute's type can't change. */
        public ProductAttributeType $type,
        public AttributeScope $scope,
        public AttributeCardinality $cardinality,
        public array $values,
    ) {}
}
