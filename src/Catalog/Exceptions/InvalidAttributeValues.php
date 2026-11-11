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

    public static function notYesAndNo(string $attribute): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.boolean-attribute-values-fixed',
            message: "[{$attribute}] is a yes-or-no attribute, but its values aren't exactly its yes and its no.",
            why: 'A boolean attribute has two values, __boolean_true and __boolean_false; they can be renamed, not added, removed or re-slugged.',
            fix: 'Pass two drafts with the slugs __boolean_true and __boolean_false, with the existing values\' ids when updating.',
        );
    }

    public static function notANumber(string $attribute, string $name): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.attribute-value-not-a-number',
            message: "[{$attribute}] is a number attribute, but the value [{$name}] isn't a number.",
            why: 'A number attribute\'s value is the number its name spells, in the default locale.',
            fix: 'Name the value with a number, e.g. "180" or "2.5".',
        );
    }

    public static function typeChanged(string $attribute): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.attribute-type-changed',
            message: "[{$attribute}]'s type can't change.",
            why: 'Its values were checked against its type, and products picked them.',
            fix: 'Pass the attribute\'s own type, or create another attribute.',
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
