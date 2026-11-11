<?php

namespace Larasell\Larasell\Catalog\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

/** Attribute values were given that their attribute doesn't take. */
final class InvalidAttributeValues extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function ofAnotherAttribute(string $attribute): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.attribute-value-of-another-attribute',
            message: "A value given for [{$attribute}] belongs to another attribute.",
            why: 'Each attribute only takes its own values.',
            fix: "Pass values of [{$attribute}] only, e.g. from \$attribute->values.",
        );
    }

    public static function tooMany(string $attribute): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.too-many-attribute-values',
            message: "[{$attribute}] takes one value, but several were given.",
            why: 'Its cardinality is single.',
            fix: "Give one value, or set [{$attribute}]'s cardinality to AttributeCardinality::Multiple.",
        );
    }
}
