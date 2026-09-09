<?php

namespace Larasell\Larasell\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Customer;
use Larasell\Larasell\Models\CustomerUser;

interface StorefrontUser
{
    /** @return HasOneThrough<Customer, CustomerUser, covariant Model> */
    public function customer(): HasOneThrough;

    /** @param  array<string, mixed>  $attributes */
    public function createCustomer(array $attributes = []): Customer;

    /** @return HasMany<Cart, covariant Model> */
    public function carts(): HasMany;
}
