<?php

namespace Larasell\Larasell\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\EmailAddress;
use Larasell\Larasell\PhoneNumber;
use Larasell\Larasell\Url;
use Larasell\Larasell\VatId;

/**
 * Stores a value object that is one string, e.g. an EmailAddress, in a nullable string column.
 * The value objects cast themselves with it: `'email' => EmailAddress::class`.
 *
 * @implements CastsAttributes<EmailAddress|PhoneNumber|Url|VatId|null, EmailAddress|PhoneNumber|Url|VatId|null>
 */
final readonly class StringValueCast implements CastsAttributes
{
    /** @param class-string<EmailAddress|PhoneNumber|Url|VatId> $class */
    public function __construct(private string $class) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): EmailAddress|PhoneNumber|Url|VatId|null
    {
        return is_string($value) ? $this->class::of($value) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value?->value();
    }
}
