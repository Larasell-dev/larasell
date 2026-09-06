<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Larasell\Larasell\Dimensions;

/**
 * @implements CastsAttributes<Dimensions, Dimensions|array{length: int|string, width: int|string, height: int|string, unit: string}>
 */
class DimensionsCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Dimensions
    {
        if ($value === null) {
            throw new InvalidArgumentException("The [{$key}] attribute is required.");
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a dimensions payload.");
        }

        return Dimensions::fromArray($decoded);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof Dimensions) {
            return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
        }

        if (is_array($value)) {
            return json_encode(Dimensions::fromArray($value)->toArray(), JSON_THROW_ON_ERROR);
        }

        throw new InvalidArgumentException("The [{$key}] attribute must be a Dimensions instance or dimensions payload.");
    }
}
