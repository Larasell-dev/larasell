<?php

namespace Larasell\Larasell\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

final class InvalidVatId extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function of(string $vatId): self
    {
        return self::structured(
            errorCode: 'larasell.invalid-vat-id',
            message: "[{$vatId}] isn't a VAT ID.",
            why: 'A VAT ID starts with the prefix of an EU country or XI and follows that country\'s format.',
            fix: 'Pass a VAT ID such as DE123456789 or ATU12345678, or null when there is none.',
        );
    }
}
