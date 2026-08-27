<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Translatable;

/**
 * @implements CastsAttributes<Translatable|null, Translatable|non-empty-array<string, string>|string|null>
 */
class NullableTranslatableCast implements CastsAttributes
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
}
