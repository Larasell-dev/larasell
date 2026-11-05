<?php

namespace Larasell\Larasell\Checkout;

use Larasell\Larasell\Ids\Id;
use Larasell\Larasell\Models\Order;

final readonly class OrderId extends Id
{
    protected static function model(): string
    {
        return Order::class;
    }
}
