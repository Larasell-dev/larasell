<?php

namespace Larasell\Larasell\Customers\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Customers\CustomerContactId;
use Larasell\Larasell\Customers\CustomerId;

/** An account can only point at a contact of its own customer. */
final class ContactBelongsToAnotherCustomer extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function forAccount(CustomerContactId $contactId, CustomerId $customerId): self
    {
        return self::structured(
            errorCode: 'larasell.customers.contact-of-another-customer',
            message: "Contact [{$contactId}] does not belong to customer [{$customerId}].",
            why: 'The contact says who the user is within the customer, so it must be one of its contacts.',
            fix: 'Pass a contact of the same customer, or none.',
        );
    }
}
