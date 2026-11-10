<?php

namespace Larasell\Larasell\Customers;

use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Ids\Id;

final readonly class CustomerId extends Id
{
    protected static function model(): string
    {
        return Customer::class;
    }
}
