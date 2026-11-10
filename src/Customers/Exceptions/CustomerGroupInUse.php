<?php

namespace Larasell\Larasell\Customers\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Customers\CustomerGroupId;
use RuntimeException;

/** A group with members cannot be deleted. */
final class CustomerGroupInUse extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function hasMembers(CustomerGroupId $groupId, int $members): self
    {
        return self::structured(
            errorCode: 'larasell.customers.group-in-use',
            message: "Customer group [{$groupId}] cannot be deleted while {$members} customers are in it.",
            why: 'Deleting it would silently change what those customers get from rules that use the group.',
            fix: 'Take the customers out of the group first with RemoveCustomerFromGroup.',
        );
    }
}
