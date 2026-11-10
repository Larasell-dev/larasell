<?php

namespace Larasell\Larasell\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

final class InvalidUrl extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function of(string $value): self
    {
        return self::structured(
            errorCode: 'larasell.invalid-url',
            message: "[{$value}] isn't a web address.",
            why: 'Only http and https addresses with a host can be opened as links.',
            fix: 'Pass an address such as https://example.com or example.com, or null when there is none.',
        );
    }
}
