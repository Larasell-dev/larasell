<?php

use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Price;
use Larasell\Larasell\Taxes\TaxAmountCalculator;
use Larasell\Larasell\Taxes\TaxRate;
use Larasell\Larasell\Taxes\TaxRounding;

it('rounds exact rational tax amounts half up to minor units', function (string $amount, string $rate, TaxPriceMode $mode, string $expected) {
    $tax = new TaxAmountCalculator(new TaxRounding);

    expect($tax->calculate(Price::of($amount), TaxRate::from($rate), $mode)->amount())->toBe($expected);
})->with([
    'exclusive below half' => ['2', '19', TaxPriceMode::Exclusive, '0'],
    'exclusive above half' => ['3', '19', TaxPriceMode::Exclusive, '1'],
    'inclusive exact VAT' => ['1190', '19', TaxPriceMode::Inclusive, '190'],
    'inclusive fractional VAT' => ['100', '19', TaxPriceMode::Inclusive, '16'],
    'four decimal rate precision' => ['1000000', '19.1234', TaxPriceMode::Exclusive, '191234'],
    'arbitrary precision amount' => ['999999999999999999999999', '19', TaxPriceMode::Exclusive, '190000000000000000000000'],
]);

it('rejects invalid rounding fractions', function (string $numerator, string $denominator, string $message) {
    expect(fn () => (new TaxRounding)->divide($numerator, $denominator))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    ['-1', '100', 'A tax rounding numerator cannot be negative.'],
    ['1', '0', 'A tax rounding denominator must be positive.'],
]);
