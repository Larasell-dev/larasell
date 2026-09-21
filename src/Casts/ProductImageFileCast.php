<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<string, File|UploadedFile>
 */
class ProductImageFileCast implements CastsAttributes
{
    public bool $withoutObjectCaching = true;

    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("The [{$key}] attribute is required.");
        }

        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! $value instanceof File && ! $value instanceof UploadedFile) {
            throw new InvalidArgumentException("The [{$key}] attribute must be a file.");
        }

        $stored = Storage::disk((string) config('larasell.images.disk'))->putFile(
            (string) config('larasell.images.path'),
            $value,
            ['visibility' => config('larasell.images.visibility')],
        );

        if ($stored === false) {
            throw new InvalidArgumentException("The [{$key}] file could not be stored.");
        }

        return $stored;
    }
}
