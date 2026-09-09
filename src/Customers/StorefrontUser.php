<?php

namespace Larasell\Larasell\Customers;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Larasell\Larasell\Contracts\StorefrontUser as StorefrontUserContract;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Customer;
use Larasell\Larasell\Models\CustomerUser;
use Larasell\Larasell\Models\ModelRegistry;
use LogicException;

/**
 * @property string|null $name
 */
trait StorefrontUser
{
    /** @return HasOneThrough<Customer, CustomerUser, $this> */
    public function customer(): HasOneThrough
    {
        return $this->hasOneThrough(
            app(ModelRegistry::class)->customer->class(),
            CustomerUser::class,
            'user_id',
            'id',
            $this->getKeyName(),
            'customer_id',
        );
    }

    /** @param  array<string, mixed>  $attributes */
    public function createCustomer(array $attributes = []): Customer
    {
        if ($this->customer()->exists()) {
            throw new LogicException('A user can belong to only one customer.');
        }

        $attributes = $this->customerAttributesFromUser($attributes);

        /** @var Customer $customer */
        $customer = app(ModelRegistry::class)->customer->query()->create($attributes);
        $customer->users()->attach($this->getKey());

        return $customer;
    }

    /** @return HasMany<Cart, $this> */
    public function carts(): HasMany
    {
        return $this->hasMany(app(ModelRegistry::class)->cart->class(), 'user_id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function customerAttributesFromUser(array $attributes): array
    {
        if (isset($attributes['first_name'])) {
            $attributes['last_name'] ??= '';

            return $attributes;
        }

        $parts = explode(' ', trim((string) $this->name), 2);

        return [
            'first_name' => $parts[0] === '' ? $this->name ?? '' : $parts[0],
            'last_name' => $parts[1] ?? '',
            ...$attributes,
        ];
    }
}

/**
 * @internal
 */
abstract class AuthenticatableStorefrontUser extends Authenticatable implements StorefrontUserContract
{
    use StorefrontUser;
}
