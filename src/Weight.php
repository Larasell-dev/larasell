<?php

namespace Larasell\Larasell;

use InvalidArgumentException;
use JsonSerializable;
use Larasell\Larasell\Enums\WeightUnit;
use Larasell\Larasell\Measurements\ComparesMeasurements;
use Larasell\Larasell\Measurements\Decimal;

final readonly class Weight implements JsonSerializable
{
    use ComparesMeasurements;

    /** @var numeric-string */
    private string $amount;

    private WeightUnit $unit;

    public function __construct(int|string $amount, WeightUnit|string $unit)
    {
        $this->amount = Decimal::normalize($amount);
        $this->unit = WeightUnit::parse($unit);
    }

    public static function of(int|string $amount, WeightUnit|string $unit): self
    {
        return new self($amount, $unit);
    }

    /** @param array<array-key, mixed> $value */
    public static function fromArray(array $value): self
    {
        if (! array_key_exists('amount', $value) || (! is_int($value['amount']) && ! is_string($value['amount']))) {
            throw new InvalidArgumentException('A weight payload must contain a numeric amount.');
        }

        if (! isset($value['unit']) || ! is_string($value['unit'])) {
            throw new InvalidArgumentException('A weight payload must contain a unit.');
        }

        return self::of($value['amount'], $value['unit']);
    }

    public function unit(): WeightUnit
    {
        return $this->unit;
    }

    /** @return numeric-string */
    public function amount(?WeightUnit $unit = null): string
    {
        if ($unit === null || $unit === $this->unit) {
            return $this->amount;
        }

        return $this->to($unit)->amount();
    }

    /** @return numeric-string */
    public function grams(): string
    {
        return $this->unit->toGrams($this->amount);
    }

    public function to(WeightUnit|string $unit): self
    {
        $unit = WeightUnit::parse($unit);

        if ($unit === $this->unit) {
            return $this;
        }

        return new self($unit->fromGrams($this->grams()), $unit);
    }

    public function add(self $weight): self
    {
        return new self($this->unit->fromGrams(Decimal::add($this->grams(), $weight->grams())), $this->unit);
    }

    public function subtract(self $weight): self
    {
        return new self($this->unit->fromGrams(Decimal::subtract($this->grams(), $weight->grams())), $this->unit);
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException('Measurement multipliers cannot be negative.');
        }

        return new self(Decimal::multiply($this->amount, (string) $multiplier), $this->unit);
    }

    /**
     * @return array{amount: string, unit: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount(),
            'unit' => $this->unit->value,
        ];
    }

    /**
     * @return array{amount: string, unit: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return numeric-string */
    protected function comparableAmount(): string
    {
        return $this->grams();
    }
}
