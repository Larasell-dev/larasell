<?php

namespace Larasell\Larasell\Catalog\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

/** A variant field was inherited, overridden or cleared in a way its field doesn't allow. */
final class InvalidInheritance extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function notInheritable(string $field): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.field-not-inheritable',
            message: "A variant cannot inherit, override or clear [{$field}].",
            why: 'Only an allowlist of fields comes from the product; identifiers belong to the variant, prices and stock to other modules.',
            fix: 'Write the field on the variant directly, or use one of ProductVariant::INHERITABLE and ProductVariant::TRANSLATED.',
        );
    }

    public static function overrideWithoutValue(string $field): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.override-without-value',
            message: "The variant overrides [{$field}] but has no value for it.",
            why: 'An override is the variant\'s own value; no value at all is what clearing the field means.',
            fix: "Give [{$field}] a value, or clear it instead.",
        );
    }

    public static function valueWithoutOverride(string $field): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.value-without-override',
            message: "The variant has a value for [{$field}] without overriding it.",
            why: 'A value the variant inherits or clears would never be read.',
            fix: "Set [{$field}_inheritance] to overridden as well, e.g. with \$variant->override('{$field}', \$value).",
        );
    }

    public static function notClearable(string $field): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.field-not-clearable',
            message: "A variant cannot clear [{$field}].",
            why: 'Every variant needs a value for it.',
            fix: 'Inherit the product\'s value or override it.',
        );
    }
}
