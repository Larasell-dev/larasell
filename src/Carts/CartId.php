<?php

namespace Larasell\Larasell\Carts;

use Larasell\Larasell\Ids\Id;
use Larasell\Larasell\Models\Cart;

final readonly class CartId extends Id
{
    protected static function model(): string
    {
        return Cart::class;
    }
}
