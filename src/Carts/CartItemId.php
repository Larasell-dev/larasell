<?php

namespace Larasell\Larasell\Carts;

use Larasell\Larasell\Ids\Id;
use Larasell\Larasell\Models\CartItem;

final readonly class CartItemId extends Id
{
    protected static function model(): string
    {
        return CartItem::class;
    }
}
