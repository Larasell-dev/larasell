<?php

namespace Larasell\Larasell\Customers\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Customers\CustomerId;
use RuntimeException;

/** The user already acts for this customer. */
final class AccountAlreadyLinked extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function toCustomer(CustomerId $customerId): self
    {
        return self::structured(
            errorCode: 'larasell.customers.account-already-linked',
            message: "The account is already linked to customer [{$customerId}].",
            why: 'A user is linked to each customer at most once.',
            fix: 'Use the existing CustomerAccount, or unlink it first to link it to another contact.',
        );
    }
}
