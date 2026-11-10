<?php

namespace Larasell\Larasell\Customers\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\CustomerType;

/** A person profile was given for a company customer, or a company profile for an individual. */
final class ProfileDoesNotMatchType extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function forCustomer(CustomerId $customerId, CustomerType $type): self
    {
        return self::structured(
            errorCode: 'larasell.customers.profile-does-not-match-type',
            message: "Customer [{$customerId}] is of type [{$type->value}] and cannot take a profile of the other type.",
            why: 'A customer keeps the type it was created with: an individual has a person profile, a company a company profile.',
            fix: 'Pass PersonProfileData for individuals and CompanyProfileData for companies, or create a new customer of the other type.',
        );
    }
}
