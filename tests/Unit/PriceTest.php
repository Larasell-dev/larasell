<?php

use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Price;

it('stores a currency-independent amount in minor units', function () {
    $price = Price::of('001299');

    expect($price->amount())->toBe('1299')
        ->and($price->toArray())->toBe([
            'amount' => '1299',
        ]);
});

it('supports arbitrary precision addition and multiplication', function () {
    $price = Price::of('999999999999999999999999');

    expect($price->add(Price::of(1))->amount())->toBe('1000000000000000000000000')
        ->and($price->multiply(3)->amount())->toBe('2999999999999999999999997');
});

it('formats an amount with the supplied currency', function () {
    expect(Price::format(Price::of(1299), Currency::USD, 'en_US'))->toBe('$12.99')
        ->and(Price::format(Price::of(1299), Currency::JPY, 'en_US'))->toBe('¥1,299');
});

it('rejects amounts that are not integer minor units', function () {
    Price::of('12.99');
})->throws(InvalidArgumentException::class, 'Price amount must be an integer value in minor units.');

it('defines the supported currencies minor unit digits', function () {
    expect(Currency::JPY->minorUnitDigits())->toBe(0)
        ->and(Currency::EUR->minorUnitDigits())->toBe(2)
        ->and(Currency::USD->minorUnitDigits())->toBe(2)
        ->and(Currency::BHD->minorUnitDigits())->toBe(3)
        ->and(Currency::KWD->minorUnitDigits())->toBe(3)
        ->and(Currency::ISK->minorUnitDigits())->toBe(0)
        ->and(Currency::UGX->minorUnitDigits())->toBe(0)
        ->and(Currency::VND->minorUnitDigits())->toBe(0);
});

it('supports all Stripe presentment currencies', function () {
    $codes = array_map(fn (Currency $currency) => $currency->value, Currency::cases());

    expect($codes)->toHaveCount(139)
        ->toContain('USD', 'EUR', 'GBP', 'JPY', 'BHD', 'XCG', 'SLE', 'STD', 'XOF', 'ZMW')
        ->not->toContain('STN', 'XAU', 'USN', 'CLF', 'ZWG', 'VES');
});
