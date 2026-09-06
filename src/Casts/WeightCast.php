<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Larasell\Larasell\Weight;

/**
 * @implements CastsAttributes<Weight, Weight|array{amount: int|string, unit: string}>
 */
class WeightCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Weight
    {
        if ($value === null) {
            throw new InvalidArgumentException("The [{$key}] attribute is required.");
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a weight payload.");
        }

        return Weight::fromArray($decoded);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof Weight) {
            return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
        }

        if (is_array($value)) {
            return json_encode(Weight::fromArray($value)->toArray(), JSON_THROW_ON_ERROR);
        }

        throw new InvalidArgumentException("The [{$key}] attribute must be a Weight instance or weight payload.");
    }
}
