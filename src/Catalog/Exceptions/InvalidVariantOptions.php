<?php

namespace Larasell\Larasell\Catalog\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

/** A product's options or a variant's combination of option values isn't valid. */
final class InvalidVariantOptions extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function incomplete(): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.variant-combination-incomplete',
            message: 'A combination needs exactly one value of each of the product\'s options.',
            why: 'Every variant of a product with options is told apart by one value per option.',
            fix: 'Pass one value for each option, and no value twice for the same option.',
        );
    }

    public static function notOffered(string $value): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.option-value-not-offered',
            message: "The product doesn't offer the option value [{$value}].",
            why: 'Variants can only use values the product offers for its options.',
            fix: 'Add the value to the product first with Product::addOptionValue().',
        );
    }

    public static function duplicate(): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.variant-combination-exists',
            message: 'The product already has a variant with this combination.',
            why: 'Two variants with the same option values couldn\'t be told apart.',
            fix: 'Edit the existing variant instead.',
        );
    }

    public static function alreadyUsed(string $definition): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.option-already-used',
            message: "The product already uses the option [{$definition}].",
            why: 'A product uses each option once.',
            fix: 'Add values to the existing option with Product::addOptionValue().',
        );
    }

    public static function noValues(): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.option-without-values',
            message: 'An option needs at least one value of its own definition.',
            why: 'Existing variants get the option\'s first value.',
            fix: 'Pass one or more values of the definition.',
        );
    }

    public static function wouldDeleteEveryVariant(): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.options-delete-every-variant',
            message: 'The change would delete every variant of the product.',
            why: 'A product always keeps at least one variant.',
            fix: 'Keep at least one value that a variant uses, or remove the whole option instead.',
        );
    }
}
