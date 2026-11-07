<?php

namespace Larasell\Larasell\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use LogicException;

/**
 * Extensions got two objects of the same class, so one would silently replace the other.
 */
final class DuplicateExtension extends LogicException implements StructuredException
{
    use HasStructuredException;

    public static function of(string $class): self
    {
        return self::structured(
            errorCode: 'larasell.extensions.duplicate',
            message: "Extensions got more than one [{$class}].",
            why: 'Listeners read an extension by its class, so a second one would silently replace the first.',
            fix: 'Pass one object per class: give each plugin its own payload class, or combine the data into one object.',
        );
    }
}
