<?php

namespace Larasell\Larasell\Customers\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use RuntimeException;

/** Customer group codes are unique. */
final class DuplicateCustomerGroupCode extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function code(string $code): self
    {
        return self::structured(
            errorCode: 'larasell.customers.duplicate-group-code',
            message: "A customer group with the code [{$code}] already exists.",
            why: 'Other modules and imports find groups by their code.',
            fix: 'Pick another code, or update the existing group.',
        );
    }
}
