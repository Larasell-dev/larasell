<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Larasell\Larasell\Address;

/** @implements CastsAttributes<Address, Address|array<string, mixed>> */
class AddressCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Address
    {
        $address = json_decode($value, true);

        if (! is_array($address)) {
            throw new InvalidArgumentException("The [{$key}] attribute must be an address payload.");
        }

        return Address::fromArray($address);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (is_array($value)) {
            $value = Address::fromArray($value);
        }

        if (! $value instanceof Address) {
            throw new InvalidArgumentException("The [{$key}] attribute must be an Address instance or address payload.");
        }

        return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
    }
}
