<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Weight;

/**
 * @implements CastsAttributes<Weight|null, Weight|array{amount: int|string, unit: string}|null>
 */
class NullableWeightCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Weight
    {
        if ($value === null) {
            return null;
        }

        return (new WeightCast)->get($model, $key, $value, $attributes);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return (new WeightCast)->set($model, $key, $value, $attributes);
    }
}
