<?php

namespace Larasell\Larasell\Taxes;

use InvalidArgumentException;
use Larasell\Larasell\Enums\TaxRoundingMode;

final readonly class TaxRounding
{
    public function __construct(public TaxRoundingMode $mode = TaxRoundingMode::HalfUp) {}

    /**
     * @param  numeric-string  $numerator
     * @param  numeric-string  $denominator
     * @return numeric-string
     */
    public function divide(string $numerator, string $denominator): string
    {
        if (bccomp($numerator, '0', 0) === -1) {
            throw new InvalidArgumentException('A tax rounding numerator cannot be negative.');
        }

        if (bccomp($denominator, '0', 0) !== 1) {
            throw new InvalidArgumentException('A tax rounding denominator must be positive.');
        }

        return match ($this->mode) {
            TaxRoundingMode::HalfUp => bcdiv(
                bcadd($numerator, bcdiv($denominator, '2', 0), 0),
                $denominator,
                0,
            ),
        };
    }
}
