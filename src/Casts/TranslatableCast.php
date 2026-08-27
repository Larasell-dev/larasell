<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;
use Larasell\Larasell\Translatable;

/**
 * @implements CastsAttributes<Translatable, Translatable|non-empty-array<string, string>|string>
 */
class TranslatableCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Translatable
    {
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("The [{$key}] attribute requires at least one translation.");
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return Translatable::fromString($value, (string) config('app.fallback_locale', 'en'));
        }

        if (! is_array($decoded) || $decoded === []) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a translation payload.");
        }

        return new Translatable($decoded);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (is_string($value)) {
            $value = Translatable::fromString($value);
        } elseif (is_array($value)) {
            $value = new Translatable($value);
        }

        if (! $value instanceof Translatable) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a Translatable instance, translation payload, or string.");
        }

        return json_encode($value->all(), JSON_THROW_ON_ERROR);
    }
}
