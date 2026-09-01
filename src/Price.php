<?php

namespace Larasell\Larasell;

use InvalidArgumentException;
use JsonSerializable;
use Larasell\Larasell\Enums\Currency as LarasellCurrency;
use NumberFormatter;
use RuntimeException;

final readonly class Price implements JsonSerializable
{
    public function __construct(
        /** @var numeric-string */
        private string $amount,
    ) {
        if (! preg_match('/^-?\d+$/', $amount)) {
            throw new InvalidArgumentException('Price amount must be an integer value in minor units.');
        }

    }

    public static function of(int|string $amount): self
    {
        return new self(self::normalizeAmount($amount));
    }

    /** @param array<array-key, mixed> $value */
    public static function fromArray(array $value): self
    {
        if (! isset($value['amount']) || (! is_int($value['amount']) && ! is_string($value['amount']))) {
            throw new InvalidArgumentException('A price payload must contain an integer amount.');
        }

        return self::of($value['amount']);
    }

    /** @return numeric-string */
    public function amount(): string
    {
        return $this->amount;
    }

    public function add(self $price): self
    {
        return self::of(bcadd($this->amount, $price->amount, 0));
    }

    public function subtract(self $price): self
    {
        return self::of(bcsub($this->amount, $price->amount, 0));
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', 0) === 1;
    }

    public function greaterThan(self $price): bool
    {
        return bccomp($this->amount, $price->amount, 0) === 1;
    }

    public function multiply(int $multiplier): self
    {
        return self::of(bcmul($this->amount, (string) $multiplier, 0));
    }

    public static function format(self $price, LarasellCurrency|string $currency, ?string $locale = null): string
    {
        $currency = $currency instanceof LarasellCurrency ? $currency : LarasellCurrency::from(strtoupper($currency));
        $formatter = new NumberFormatter($locale ?? \Locale::getDefault(), NumberFormatter::CURRENCY);
        $majorAmount = bcdiv($price->amount, bcpow('10', (string) $currency->minorUnitDigits(), 0), $currency->minorUnitDigits());
        $formatted = $formatter->formatCurrency((float) $majorAmount, $currency->value);

        if ($formatted === false) {
            throw new RuntimeException('The price could not be formatted.');
        }

        return $formatted;
    }

    /**
     * @return array{amount: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount(),
        ];
    }

    /**
     * @return array{amount: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return numeric-string */
    private static function normalizeAmount(int|string $amount): string
    {
        $amount = (string) $amount;

        if (! preg_match('/^-?\d+$/', $amount)) {
            throw new InvalidArgumentException('Price amount must be an integer value in minor units.');
        }

        $negative = str_starts_with($amount, '-');
        $normalized = ltrim($negative ? substr($amount, 1) : $amount, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $normalized = $negative && $normalized !== '0' ? '-'.$normalized : $normalized;

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException('Price amount could not be normalized.');
        }

        return $normalized;
    }
}
