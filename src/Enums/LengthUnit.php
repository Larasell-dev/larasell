<?php

namespace Larasell\Larasell\Enums;

use InvalidArgumentException;
use Larasell\Larasell\Measurements\Decimal;

enum LengthUnit: string
{
    case Millimeter = 'mm';
    case Centimeter = 'cm';
    case Meter = 'm';
    case Inch = 'in';
    case Foot = 'ft';

    public static function parse(self|string $unit): self
    {
        if ($unit instanceof self) {
            return $unit;
        }

        return self::tryFrom($unit)
            ?? throw new InvalidArgumentException("Unknown length unit [{$unit}].");
    }

    /** @return numeric-string */
    public function millimetersPerUnit(): string
    {
        return match ($this) {
            self::Millimeter => '1',
            self::Centimeter => '10',
            self::Meter => '1000',
            self::Inch => '25.4',
            self::Foot => '304.8',
        };
    }

    /**
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public function toMillimeters(string $amount): string
    {
        return Decimal::multiply($amount, $this->millimetersPerUnit());
    }

    /**
     * @param  numeric-string  $millimeters
     * @return numeric-string
     */
    public function fromMillimeters(string $millimeters): string
    {
        return Decimal::divide($millimeters, $this->millimetersPerUnit());
    }
}
