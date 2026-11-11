<?php

namespace Larasell\Larasell\Catalog\Options\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

/** Option values were given that their option doesn't take. */
final class InvalidOptionValues extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function ofAnotherOption(string $option): self
    {
        return self::structured(
            errorCode: 'larasell.catalog.option-value-of-another-option',
            message: "A value given for [{$option}] belongs to another option.",
            why: 'Each option only takes its own values.',
            fix: "Pass values of [{$option}] only, e.g. from \$definition->values.",
        );
    }
}
