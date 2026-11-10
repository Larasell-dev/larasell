<?php

namespace Larasell\Larasell;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Larasell\Larasell\Casts\StringValueCast;
use Larasell\Larasell\Enums\Country;
use Larasell\Larasell\Exceptions\InvalidVatId;
use Stringable;

/**
 * A VAT identification number of an EU country or Northern Ireland, e.g. "DE123456789": its
 * country prefix and the format that country uses. Spaces, dots and dashes are removed and
 * letters uppercased. Only the format is checked, not whether the number is registered.
 */
final readonly class VatId implements Castable, Stringable
{
    /** The format after the prefix, by prefix. Greece uses EL, Northern Ireland XI. */
    private const FORMATS = [
        'AT' => 'U\d{8}',
        'BE' => '[01]\d{9}',
        'BG' => '\d{9,10}',
        'CY' => '\d{8}[A-Z]',
        'CZ' => '\d{8,10}',
        'DE' => '\d{9}',
        'DK' => '\d{8}',
        'EE' => '\d{9}',
        'EL' => '\d{9}',
        'ES' => '[A-Z0-9]\d{7}[A-Z0-9]',
        'FI' => '\d{8}',
        'FR' => '[A-HJ-NP-Z0-9]{2}\d{9}',
        'HR' => '\d{11}',
        'HU' => '\d{8}',
        'IE' => '\d{7}[A-W][A-I]?|\d[A-Z+*]\d{5}[A-W]',
        'IT' => '\d{11}',
        'LT' => '\d{9}|\d{12}',
        'LU' => '\d{8}',
        'LV' => '\d{11}',
        'MT' => '\d{8}',
        'NL' => '\d{9}B\d{2}',
        'PL' => '\d{10}',
        'PT' => '\d{9}',
        'RO' => '[1-9]\d{1,9}',
        'SE' => '\d{10}01',
        'SI' => '\d{8}',
        'SK' => '\d{10}',
        'XI' => '\d{9}|\d{12}|GD[0-4]\d{2}|HA[5-9]\d{2}',
    ];

    private function __construct(private string $value) {}

    public static function of(string $vatId): self
    {
        $vatId = strtoupper((string) preg_replace('/[\s.\-]/', '', $vatId));
        $format = self::FORMATS[substr($vatId, 0, 2)] ?? null;

        if ($format === null || preg_match('/^(?:'.$format.')$/', substr($vatId, 2)) !== 1) {
            throw InvalidVatId::of($vatId);
        }

        return new self($vatId);
    }

    public function country(): Country
    {
        return match ($prefix = substr($this->value, 0, 2)) {
            'EL' => Country::GR,
            'XI' => Country::GB,
            default => Country::from($prefix),
        };
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** @param array<mixed> $arguments */
    public static function castUsing(array $arguments): StringValueCast
    {
        return new StringValueCast(self::class);
    }
}
