<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Dimensions;

/**
 * @implements CastsAttributes<Dimensions|null, Dimensions|array{length: int|string, width: int|string, height: int|string, unit: string}|null>
 */
class NullableDimensionsCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Dimensions
    {
        if ($value === null) {
            return null;
        }

        return (new DimensionsCast)->get($model, $key, $value, $attributes);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return (new DimensionsCast)->set($model, $key, $value, $attributes);
    }
}
