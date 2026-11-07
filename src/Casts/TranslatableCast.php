<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;
use Larasell\Larasell\Settings\LocaleSettings;
use Larasell\Larasell\Translatable;

/**
 * @implements CastsAttributes<Translatable, Translatable|non-empty-array<string, string>|string>
 */
class TranslatableCast implements CastsAttributes, ComparesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Translatable
    {
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("The [{$key}] attribute requires at least one translation.");
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return Translatable::fromString($value, app(LocaleSettings::class)->default()->value);
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

    /**
     * Whether two stored values hold the same translations. MySQL and MariaDB normalize JSON, e.g.
     * {"en": "Boots"} with a space and keys reordered, so the stored strings can differ while the
     * translations don't, and Eloquent would see a change that isn't one.
     */
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        // Strict, so "10" and "1e1" differ; sorted, since the key order is all MySQL may change.
        return self::decode($firstValue) === self::decode($secondValue);
    }

    private static function decode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $value;
        }

        if (is_array($decoded)) {
            ksort($decoded);
        }

        return $decoded;
    }
}
