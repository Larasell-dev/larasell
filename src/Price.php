<?php

namespace Larasell\Larasell;

use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;
use RuntimeException;
use Larasell\Larasell\Enums\Currency as LarasellCurrency;

final readonly class Price implements JsonSerializable
{
    public function __construct(
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

    /**
     * @param array{amount: int|string} $value
     */
    public static function fromArray(array $value): self
    {
        return self::of($value['amount']);
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function add(self $price): self
    {
        return self::of(bcadd($this->amount, $price->amount, 0));
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

    private static function normalizeAmount(int|string $amount): string
    {
        $amount = (string) $amount;

        if (! preg_match('/^-?\d+$/', $amount)) {
            throw new InvalidArgumentException('Price amount must be an integer value in minor units.');
        }

        $negative = str_starts_with($amount, '-');
        $normalized = ltrim($negative ? substr($amount, 1) : $amount, '0');
        $normalized = $normalized === '' ? '0' : $normalized;

        return $negative && $normalized !== '0' ? '-'.$normalized : $normalized;
    }

}
