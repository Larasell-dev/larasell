<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Translatable;

/**
 * @implements CastsAttributes<Translatable|null, Translatable|non-empty-array<string, string>|string|null>
 */
class NullableTranslatableCast implements CastsAttributes, ComparesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Translatable
    {
        if ($value === null) {
            return null;
        }

        return (new TranslatableCast)->get($model, $key, $value, $attributes);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return (new TranslatableCast)->set($model, $key, $value, $attributes);
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return (new TranslatableCast)->compare($model, $key, $firstValue, $secondValue);
    }
}
