<?php

namespace Larasell\Larasell\Measurements;

/** @internal */
trait ComparesMeasurements
{
    /** @return numeric-string */
    abstract protected function comparableAmount(): string;

    public function equals(self $other): bool
    {
        return bccomp($this->comparableAmount(), $other->comparableAmount(), Decimal::SCALE) === 0;
    }

    public function greaterThan(self $other): bool
    {
        return bccomp($this->comparableAmount(), $other->comparableAmount(), Decimal::SCALE) === 1;
    }

    public function lessThan(self $other): bool
    {
        return $other->greaterThan($this);
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return ! $this->lessThan($other);
    }

    public function lessThanOrEqual(self $other): bool
    {
        return ! $this->greaterThan($other);
    }

    public function isZero(): bool
    {
        return bccomp($this->comparableAmount(), '0', Decimal::SCALE) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->comparableAmount(), '0', Decimal::SCALE) === 1;
    }

    public static function max(self $first, self $second): self
    {
        return $first->greaterThan($second) ? $first : $second;
    }

    public static function min(self $first, self $second): self
    {
        return $first->lessThan($second) ? $first : $second;
    }
}
