<?php

namespace Larasell\Larasell\Customers;

use Larasell\Larasell\Customers\Models\CustomerContact;
use Larasell\Larasell\Ids\Id;

final readonly class CustomerContactId extends Id
{
    protected static function model(): string
    {
        return CustomerContact::class;
    }
}
