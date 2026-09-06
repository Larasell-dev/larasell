<?php

namespace Larasell\Larasell;

use InvalidArgumentException;
use JsonSerializable;
use Larasell\Larasell\Enums\LengthUnit;
use Larasell\Larasell\Measurements\Decimal;

final readonly class Dimensions implements JsonSerializable
{
    public function __construct(
        private Length $length,
        private Length $width,
        private Length $height,
    ) {
        if ($length->unit() !== $width->unit() || $width->unit() !== $height->unit()) {
            throw new InvalidArgumentException('Dimension axes must use the same unit.');
        }
    }

    public static function of(
        int|string $length,
        int|string $width,
        int|string $height,
        LengthUnit|string $unit,
    ): self {
        $unit = LengthUnit::parse($unit);

        return new self(
            Length::of($length, $unit),
            Length::of($width, $unit),
            Length::of($height, $unit),
        );
    }

    /** @param array<array-key, mixed> $value */
    public static function fromArray(array $value): self
    {
        foreach (['length', 'width', 'height'] as $axis) {
            if (! array_key_exists($axis, $value) || (! is_int($value[$axis]) && ! is_string($value[$axis]))) {
                throw new InvalidArgumentException('A dimensions payload must contain numeric length, width, and height.');
            }
        }

        if (! isset($value['unit']) || ! is_string($value['unit'])) {
            throw new InvalidArgumentException('A dimensions payload must contain a unit.');
        }

        return self::of($value['length'], $value['width'], $value['height'], $value['unit']);
    }

    public function unit(): LengthUnit
    {
        return $this->length->unit();
    }

    public function length(?LengthUnit $unit = null): Length
    {
        return $unit === null ? $this->length : $this->length->to($unit);
    }

    public function width(?LengthUnit $unit = null): Length
    {
        return $unit === null ? $this->width : $this->width->to($unit);
    }

    public function height(?LengthUnit $unit = null): Length
    {
        return $unit === null ? $this->height : $this->height->to($unit);
    }

    public function to(LengthUnit|string $unit): self
    {
        $unit = LengthUnit::parse($unit);

        if ($unit === $this->unit()) {
            return $this;
        }

        return new self(
            $this->length->to($unit),
            $this->width->to($unit),
            $this->height->to($unit),
        );
    }

    public function longestSide(): Length
    {
        return Length::max(Length::max($this->length, $this->width), $this->height);
    }

    public function shortestSide(): Length
    {
        return Length::min(Length::min($this->length, $this->width), $this->height);
    }

    /** @return numeric-string */
    public function volume(?LengthUnit $unit = null): string
    {
        $unit ??= $this->unit();

        return Decimal::multiply(
            Decimal::multiply($this->length->amount($unit), $this->width->amount($unit)),
            $this->height->amount($unit),
        );
    }

    public function equals(self $dimensions): bool
    {
        return $this->length->equals($dimensions->length)
            && $this->width->equals($dimensions->width)
            && $this->height->equals($dimensions->height);
    }

    public function hasEqualVolume(self $dimensions): bool
    {
        return bccomp($this->cubicMillimeters(), $dimensions->cubicMillimeters(), Decimal::SCALE) === 0;
    }

    public function hasGreaterVolumeThan(self $dimensions): bool
    {
        return bccomp($this->cubicMillimeters(), $dimensions->cubicMillimeters(), Decimal::SCALE) === 1;
    }

    public function hasLesserVolumeThan(self $dimensions): bool
    {
        return $dimensions->hasGreaterVolumeThan($this);
    }

    public function fitsInside(self $box, bool $allowRotation = true): bool
    {
        if (! $allowRotation) {
            return $this->length->lessThanOrEqual($box->length)
                && $this->width->lessThanOrEqual($box->width)
                && $this->height->lessThanOrEqual($box->height);
        }

        $item = $this->sortedSides();
        $container = $box->sortedSides();

        return $item[0]->lessThanOrEqual($container[0])
            && $item[1]->lessThanOrEqual($container[1])
            && $item[2]->lessThanOrEqual($container[2]);
    }

    /**
     * @return array{0: Length, 1: Length, 2: Length}
     */
    private function sortedSides(): array
    {
        $sides = [$this->length, $this->width, $this->height];

        usort($sides, function (Length $left, Length $right): int {
            if ($left->equals($right)) {
                return 0;
            }

            return $left->lessThan($right) ? -1 : 1;
        });

        return [$sides[0], $sides[1], $sides[2]];
    }

    /**
     * @return array{length: string, width: string, height: string, unit: string}
     */
    public function toArray(): array
    {
        return [
            'length' => $this->length->amount(),
            'width' => $this->width->amount(),
            'height' => $this->height->amount(),
            'unit' => $this->unit()->value,
        ];
    }

    /**
     * @return array{length: string, width: string, height: string, unit: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return numeric-string */
    private function cubicMillimeters(): string
    {
        return Decimal::multiply(
            Decimal::multiply($this->length->millimeters(), $this->width->millimeters()),
            $this->height->millimeters(),
        );
    }
}
