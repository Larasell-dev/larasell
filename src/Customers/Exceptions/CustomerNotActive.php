<?php

namespace Larasell\Larasell\Customers\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\CustomerStatus;
use RuntimeException;

/** A blocked or archived customer cannot get new accounts. */
final class CustomerNotActive extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function cannotLinkAccounts(CustomerId $customerId, CustomerStatus $status): self
    {
        return self::structured(
            errorCode: 'larasell.customers.not-active',
            message: "Customer [{$customerId}] is [{$status->value}], so no account can be linked to it.",
            why: 'Blocked and archived customers must not gain new ways to sign in and buy.',
            fix: 'Make the customer active with ChangeCustomerStatus first.',
        );
    }
}
