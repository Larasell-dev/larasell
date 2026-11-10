<?php

namespace Larasell\Larasell\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

final class InvalidPhoneNumber extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function of(string $value): self
    {
        return self::structured(
            errorCode: 'larasell.invalid-phone-number',
            message: "[{$value}] isn't a phone number.",
            why: 'Phone numbers are kept as typed, so only digits, spaces and + ( ) - / . are allowed.',
            fix: 'Pass a number such as +49 30 123456 with at most 32 characters, or null when there is none.',
        );
    }
}
