<?php

namespace Larasell\Larasell\Catalog\Options\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use RuntimeException;

/** Variants use an option or one of its values, so it can't be deleted. */
final class OptionInUse extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    /** The value's slug, or null when the whole option was to be deleted. */
    public private(set) ?string $value = null;

    /** The variants that use it. */
    public private(set) int $variants = 0;

    /** The products those variants belong to. */
    public private(set) int $products = 0;

    public static function option(string $option, int $variants, int $products): self
    {
        return self::usedBy(self::structured(
            errorCode: 'larasell.catalog.option-in-use',
            message: "[{$option}] can't be deleted while {$variants} variants of {$products} products use it.",
            why: 'Each variant is told apart by its value of every option of its product.',
            fix: 'Delete those variants on their products first.',
        ), null, $variants, $products);
    }

    public static function value(string $option, string $value, int $variants, int $products): self
    {
        return self::usedBy(self::structured(
            errorCode: 'larasell.catalog.option-value-in-use',
            message: "[{$option}]'s value [{$value}] can't be deleted while {$variants} variants of {$products} products use it.",
            why: 'Without it, those variants would have no value of the option.',
            fix: 'Delete those variants on their products first.',
        ), $value, $variants, $products);
    }

    private static function usedBy(self $exception, ?string $value, int $variants, int $products): self
    {
        $exception->value = $value;
        $exception->variants = $variants;
        $exception->products = $products;

        return $exception;
    }
}
