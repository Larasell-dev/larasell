<?php

namespace Larasell\Larasell\Customers;

use Larasell\Larasell\Customers\Models\CustomerAccount;
use Larasell\Larasell\Ids\Id;

final readonly class CustomerAccountId extends Id
{
    protected static function model(): string
    {
        return CustomerAccount::class;
    }
}
