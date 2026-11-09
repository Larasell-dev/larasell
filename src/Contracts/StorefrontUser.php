<?php

namespace Larasell\Larasell\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Models\Cart;

interface StorefrontUser
{
    /** @return HasMany<Cart, covariant Model> */
    public function carts(): HasMany;
}
