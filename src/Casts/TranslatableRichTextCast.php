<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\RichText\RichText;
use Larasell\Larasell\RichText\TranslatableRichText;
use Larasell\Larasell\Translatable;

/**
 * Stores rich text per locale like a nullable Translatable, one Portable Text document per locale. Plain
 * text, stored or set, is read as one paragraph per line.
 *
 * @implements CastsAttributes<TranslatableRichText|null, TranslatableRichText|RichText|Translatable|array<string, RichText|string>|string|null>
 */
class TranslatableRichTextCast implements CastsAttributes, ComparesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?TranslatableRichText
    {
        $stored = (new NullableTranslatableCast)->get($model, $key, $value, $attributes);

        return $stored === null ? null : TranslatableRichText::fromTranslatable($stored);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $value = match (true) {
            $value === null, $value instanceof TranslatableRichText => $value,
            $value instanceof RichText => TranslatableRichText::of($value),
            $value instanceof Translatable => TranslatableRichText::fromTranslatable($value),
            is_string($value) => TranslatableRichText::of(RichText::fromStored($value)),
            default => new TranslatableRichText(array_map(
                fn (RichText|string $text): RichText => $text instanceof RichText ? $text : RichText::fromStored($text),
                $value,
            )),
        };

        return (new NullableTranslatableCast)->set($model, $key, $value?->toTranslatable(), $attributes);
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return (new NullableTranslatableCast)->compare($model, $key, $firstValue, $secondValue);
    }
}
