<?php

namespace Larasell\Larasell;

use InvalidArgumentException;

final readonly class Barcode
{
    private function __construct(
        private string $value,
    ) {}

    public static function of(string $value): self
    {
        $digits = preg_replace('/[\s-]/', '', $value) ?? '';

        if ($digits === '' || ! ctype_digit($digits)) {
            throw new InvalidArgumentException('A barcode must contain only digits.');
        }

        $length = strlen($digits);

        if (! in_array($length, [8, 12, 13, 14], true)) {
            throw new InvalidArgumentException('A barcode must be an EAN-8, UPC-A, EAN-13, or GTIN-14.');
        }

        if (! self::hasValidCheckDigit($digits)) {
            throw new InvalidArgumentException('A barcode must have a valid check digit.');
        }

        return new self($digits);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $barcode): bool
    {
        return $this->value === $barcode->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function hasValidCheckDigit(string $digits): bool
    {
        $body = substr($digits, 0, -1);
        $checkDigit = (int) substr($digits, -1);
        $sum = 0;
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $body[$length - 1 - $i];
            $sum += $digit * ($i % 2 === 0 ? 3 : 1);
        }

        return $checkDigit === (10 - ($sum % 10)) % 10;
    }
}
