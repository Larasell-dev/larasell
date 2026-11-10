<?php

namespace Larasell\Larasell\Customers;

use Larasell\Larasell\Customers\Models\CustomerGroup;
use Larasell\Larasell\Ids\Id;

final readonly class CustomerGroupId extends Id
{
    protected static function model(): string
    {
        return CustomerGroup::class;
    }
}
