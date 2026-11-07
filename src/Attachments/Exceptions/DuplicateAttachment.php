<?php

namespace Larasell\Larasell\Attachments\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use LogicException;

/**
 * Attachments got two objects of the same class, so one would silently replace the other.
 */
final class DuplicateAttachment extends LogicException implements StructuredException
{
    use HasStructuredException;

    public static function of(string $class): self
    {
        return self::structured(
            errorCode: 'larasell.attachments.duplicate',
            message: "Attachments got more than one [{$class}].",
            why: 'Listeners read an attachment by its class, so a second one would silently replace the first.',
            fix: 'Pass one object per class: give each plugin its own payload class, or combine the data into one object.',
        );
    }
}
