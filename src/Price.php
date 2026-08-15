<?php

namespace Larasell\Larasell;

use JsonSerializable;
use Larasell\Larasell\Enums\Currency as LarasellCurrency;
use Money\Currency;
use Money\Money;

class Price implements JsonSerializable
{
    public function __construct(
        private readonly Money $money
    ) {}

    public static function of(int|string $amount, LarasellCurrency|string $currency): self
    {
        return new self(new Money($amount, new Currency(self::normalizeCurrencyCode($currency))));
    }

    public static function fromMoney(Money $money): self
    {
        return new self($money);
    }

    /**
     * @param array{amount: int|string, currency: LarasellCurrency|string} $value
     */
    public static function fromArray(array $value): self
    {
        return self::of($value['amount'], $value['currency']);
    }

    public function money(): Money
    {
        return $this->money;
    }

    public function amount(): string
    {
        return $this->money->getAmount();
    }

    public function currency(): ?LarasellCurrency
    {
        return LarasellCurrency::tryFrom($this->currencyCodeValue());
    }

    public function currencyCode(): string
    {
        return $this->currencyCodeValue();
    }

    /**
     * @return array{amount: string, currency: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount(),
            'currency' => $this->currencyCode(),
        ];
    }

    /**
     * @return array{amount: string, currency: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function normalizeCurrencyCode(LarasellCurrency|string $currency): string
    {
        return $currency instanceof LarasellCurrency ? $currency->value : $currency;
    }

    private function currencyCodeValue(): string
    {
        return $this->money->getCurrency()->getCode();
    }
}
