<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Larasell\Larasell\Price;
use Money\Money;

/**
 * @implements CastsAttributes<Price, Price|Money|array{amount: int|string, currency: \Larasell\Larasell\Enums\Currency|string}>
 */
class PriceCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Price
    {
        if ($value === null) {
            throw new InvalidArgumentException("The [{$key}] attribute is required.");
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a price payload.");
        }

        return Price::fromArray($decoded);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof Price) {
            return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
        }

        if ($value instanceof Money) {
            return json_encode(Price::fromMoney($value)->toArray(), JSON_THROW_ON_ERROR);
        }

        if (is_array($value)) {
            return json_encode(Price::fromArray($value)->toArray(), JSON_THROW_ON_ERROR);
        }

        throw new InvalidArgumentException("The [{$key}] attribute must be a Price instance, Money instance, or price payload.");
    }
}
