<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;
use Larasell\Larasell\Images\Placeholder;
use Larasell\Larasell\Models\ProductImage;

/**
 * @implements CastsAttributes<Placeholder|null, mixed>
 */
class PlaceholderCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Placeholder
    {
        if (($model instanceof ProductImage && $model->isSvg()) || $value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a placeholder payload.");
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a placeholder payload.");
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a placeholder payload.");
        }

        return Placeholder::fromArray($decoded);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Placeholder) {
            return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a Placeholder instance or placeholder payload.");
        }

        return json_encode(Placeholder::fromArray($value)->toArray(), JSON_THROW_ON_ERROR);
    }
}
