<?php

namespace Larasell\Larasell\Taxes;

use InvalidArgumentException;
use JsonSerializable;

final readonly class TaxRate implements JsonSerializable
{
    private const SCALE = 4;

    private function __construct(
        /** @var numeric-string */
        private string $percentage,
    ) {}

    public static function from(string $percentage): self
    {
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $percentage) || ! is_numeric($percentage)) {
            throw new InvalidArgumentException('A tax rate must be a non-negative decimal string with at most four decimal places.');
        }

        if (bccomp($percentage, '100', self::SCALE) === 1) {
            throw new InvalidArgumentException('A tax rate cannot exceed 100 percent.');
        }

        return new self(bcadd($percentage, '0', self::SCALE));
    }

    public function percentage(): string
    {
        return $this->percentage;
    }

    /** @return numeric-string */
    public function scaledPercentage(): string
    {
        return bcmul($this->percentage, '10000', 0);
    }

    /** @return array{percentage: string} */
    public function toArray(): array
    {
        return ['percentage' => $this->percentage];
    }

    /** @return array{percentage: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
