<?php

namespace Larasell\Larasell\Carts;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Larasell\Larasell\Contracts\StorefrontUser as StorefrontUserContract;
use Larasell\Larasell\Models\Cart;

trait StorefrontUser
{
    /** @return HasMany<Cart, $this> */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'user_id');
    }
}

/**
 * @internal
 */
abstract class AuthenticatableStorefrontUser extends Authenticatable implements StorefrontUserContract
{
    use StorefrontUser;
}
