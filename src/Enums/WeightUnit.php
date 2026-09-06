<?php

namespace Larasell\Larasell\Enums;

use InvalidArgumentException;
use Larasell\Larasell\Measurements\Decimal;

enum WeightUnit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Ounce = 'oz';
    case Pound = 'lb';

    public static function parse(self|string $unit): self
    {
        if ($unit instanceof self) {
            return $unit;
        }

        return self::tryFrom($unit)
            ?? throw new InvalidArgumentException("Unknown weight unit [{$unit}].");
    }

    /** @return numeric-string */
    public function gramsPerUnit(): string
    {
        return match ($this) {
            self::Gram => '1',
            self::Kilogram => '1000',
            self::Ounce => '28.349523125',
            self::Pound => '453.59237',
        };
    }

    /**
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public function toGrams(string $amount): string
    {
        return Decimal::multiply($amount, $this->gramsPerUnit());
    }

    /**
     * @param  numeric-string  $grams
     * @return numeric-string
     */
    public function fromGrams(string $grams): string
    {
        return Decimal::divide($grams, $this->gramsPerUnit());
    }
}
