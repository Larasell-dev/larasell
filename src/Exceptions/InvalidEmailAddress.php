<?php

namespace Larasell\Larasell\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

final class InvalidEmailAddress extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function of(string $value): self
    {
        return self::structured(
            errorCode: 'larasell.invalid-email-address',
            message: "[{$value}] isn't an email address.",
            why: 'Mail sent to it could not be delivered.',
            fix: 'Pass an address such as erika@example.com, or null when there is none.',
        );
    }
}
