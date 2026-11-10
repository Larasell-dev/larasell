<?php

namespace Larasell\Larasell\Customers\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Customers\CustomerId;
use RuntimeException;

/** Only company customers have contacts. */
final class ContactsNeedACompany extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function forCustomer(CustomerId $customerId): self
    {
        return self::structured(
            errorCode: 'larasell.customers.contacts-need-a-company',
            message: "Customer [{$customerId}] is an individual and cannot have contacts.",
            why: 'Contacts are the people who act for a company; an individual acts for themselves.',
            fix: 'Add the contact to a company customer, or store the details in the person profile.',
        );
    }
}
