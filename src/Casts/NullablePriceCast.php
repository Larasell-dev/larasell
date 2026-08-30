<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Price;

/**
 * @implements CastsAttributes<Price|null, Price|array{amount: int|string}|null>
 */
class NullablePriceCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Price
    {
        if ($value === null) {
            return null;
        }

        return (new PriceCast)->get($model, $key, $value, $attributes);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return (new PriceCast)->set($model, $key, $value, $attributes);
    }
}
