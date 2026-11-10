<?php

namespace Larasell\Larasell\Customers\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Customers\CustomerGroupDescription;

final class InvalidCustomerGroupDescription extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function of(string $description): self
    {
        return self::structured(
            errorCode: 'larasell.customers.invalid-group-description',
            message: $description === ''
                ? 'A customer group description is empty.'
                : 'A customer group description has '.mb_strlen($description).' characters.',
            why: 'A description tells admins what the group is for; it is at most '.CustomerGroupDescription::MAX_LENGTH.' characters.',
            fix: 'Pass null instead of an empty description, or shorten it.',
        );
    }
}
