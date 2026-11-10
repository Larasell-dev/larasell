<?php

namespace Larasell\Larasell\Customers\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Customers\CustomerGroupCode;

final class InvalidCustomerGroupCode extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function of(string $code): self
    {
        return self::structured(
            errorCode: 'larasell.customers.invalid-group-code',
            message: "[{$code}] isn't a customer group code.",
            why: 'Other modules, rules and imports refer to a group by its code, so it must stay plain and short.',
            fix: 'Use lowercase letters and digits joined by - or _, e.g. "wholesale-premium", at most '.CustomerGroupCode::MAX_LENGTH.' characters.',
        );
    }
}
