<?php

namespace Larasell\Larasell\Customers;

use Larasell\Larasell\Customers\Models\CustomerAddress;
use Larasell\Larasell\Ids\Id;

final readonly class CustomerAddressId extends Id
{
    protected static function model(): string
    {
        return CustomerAddress::class;
    }
}
