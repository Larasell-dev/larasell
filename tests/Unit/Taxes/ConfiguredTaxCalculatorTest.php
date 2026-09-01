<?php

use Larasell\Larasell\Address;
use Larasell\Larasell\Contracts\TaxRateResolver;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\TaxResultStatus;
use Larasell\Larasell\Enums\TaxTreatment;
use Larasell\Larasell\Price;
use Larasell\Larasell\Taxes\ConfiguredTaxCalculator;
use Larasell\Larasell\Taxes\DestinationTaxJurisdictionResolver;
use Larasell\Larasell\Taxes\TaxableLine;
use Larasell\Larasell\Taxes\TaxCalculationContext;
use Larasell\Larasell\Taxes\TaxJurisdiction;

it('calculates configured exclusive taxes per line', function () {
    $result = configuredTaxCalculator()->calculate(taxContext(
        TaxPriceMode::Exclusive,
        new TaxableLine('product:1', Price::of(1000), 'standard'),
        new TaxableLine('product:2', Price::of(1000), 'reduced'),
        new TaxableLine('shipping', Price::of(500), 'shipping'),
    ));

    expect($result->status)->toBe(TaxResultStatus::Calculated)
        ->and($result->taxableAmount()->amount())->toBe('2500')
        ->and($result->taxAmount()->amount())->toBe('355')
        ->and($result->lines[0]->taxAmount->amount())->toBe('190')
        ->and($result->lines[1]->taxAmount->amount())->toBe('70')
        ->and($result->lines[2]->taxAmount->amount())->toBe('95');
});

it('extracts configured inclusive taxes from line amounts', function () {
    $result = configuredTaxCalculator()->calculate(taxContext(
        TaxPriceMode::Inclusive,
        new TaxableLine('standard', Price::of(1190), 'standard'),
        new TaxableLine('reduced', Price::of(1070), 'reduced'),
    ));

    expect($result->taxableAmount()->amount())->toBe('2000')
        ->and($result->taxAmount()->amount())->toBe('260')
        ->and($result->lines[0]->taxAmount->amount())->toBe('190')
        ->and($result->lines[1]->taxAmount->amount())->toBe('70');
});

it('rounds tax to minor units half up for the initial calculator', function () {
    $result = configuredTaxCalculator()->calculate(taxContext(
        TaxPriceMode::Exclusive,
        new TaxableLine('line', Price::of(3), 'standard'),
    ));

    expect($result->taxAmount()->amount())->toBe('1');
});

it('calculates mixed rates from discounted amounts and rounds each line separately', function () {
    $result = configuredTaxCalculator()->calculate(taxContext(
        TaxPriceMode::Exclusive,
        new TaxableLine('standard', Price::of(1000), 'standard', discountAmount: Price::of(100)),
        new TaxableLine('reduced', Price::of(1000), 'reduced', discountAmount: Price::of(200)),
        TaxableLine::shipping('shipping', Price::of(3)),
    ));

    expect($result->taxableAmount()->amount())->toBe('1703')
        ->and($result->taxAmount()->amount())->toBe('228')
        ->and($result->lines[0]->taxAmount->amount())->toBe('171')
        ->and($result->lines[0]->discountAmount->amount())->toBe('100')
        ->and($result->lines[1]->taxAmount->amount())->toBe('56')
        ->and($result->lines[2]->taxAmount->amount())->toBe('1');
});

it('extracts inclusive tax after discounts', function () {
    $result = configuredTaxCalculator()->calculate(taxContext(
        TaxPriceMode::Inclusive,
        new TaxableLine('standard', Price::of(1190), 'standard', discountAmount: Price::of(119)),
    ));

    expect($result->taxableAmount()->amount())->toBe('900')
        ->and($result->taxAmount()->amount())->toBe('171')
        ->and($result->lines[0]->discountAmount->amount())->toBe('119');
});

it('marks billing-address jurisdiction fallback as provisional', function () {
    $context = new TaxCalculationContext(
        lines: [new TaxableLine('line', Price::of(1000), 'standard')],
        currency: Currency::EUR,
        priceMode: TaxPriceMode::Exclusive,
        billingAddress: configuredTaxAddress('DE'),
    );

    $result = configuredTaxCalculator()->calculate($context);

    expect($result->status)->toBe(TaxResultStatus::Provisional)
        ->and($result->taxAmount()->amount())->toBe('190')
        ->and($result->reason)->toContain('billing address');
});

it('returns unavailable when location or category configuration is missing', function () {
    $withoutAddress = new TaxCalculationContext(
        lines: [new TaxableLine('line', Price::of(1000), 'standard')],
        currency: Currency::EUR,
        priceMode: TaxPriceMode::Exclusive,
    );
    $unknownCategory = taxContext(
        TaxPriceMode::Exclusive,
        new TaxableLine('line', Price::of(1000), 'unknown'),
    );

    expect(configuredTaxCalculator()->calculate($withoutAddress)->status)->toBe(TaxResultStatus::Unavailable)
        ->and(configuredTaxCalculator()->calculate($unknownCategory)->status)->toBe(TaxResultStatus::Unavailable);
});

it('supports zero-rated and exempt configured treatments', function (string $category, TaxTreatment $treatment, string $taxableAmount) {
    $result = configuredTaxCalculator()->calculate(taxContext(
        TaxPriceMode::Exclusive,
        new TaxableLine('line', Price::of(1000), $category),
    ));

    expect($result->status)->toBe(TaxResultStatus::Calculated)
        ->and($result->taxAmount()->amount())->toBe('0')
        ->and($result->taxableAmount()->amount())->toBe($taxableAmount)
        ->and($result->lines[0]->treatment)->toBe($treatment)
        ->and($result->lines[0]->components)->toBe([]);
})->with([
    ['zero', TaxTreatment::ZeroRated, '1000'],
    ['exempt', TaxTreatment::Exempt, '0'],
]);

it('prefers a state rate and falls back to its country rate', function () {
    config()->set('larasell.taxes.rates.DE-BE.standard.rate', '20.0000');
    config()->set('larasell.taxes.rates.DE-BE.standard.identifier', 'berlin-vat');
    config()->set('larasell.taxes.rates.DE-BE.standard.name', 'Berlin VAT');
    $resolver = app(TaxRateResolver::class);

    expect($resolver->resolve('standard', new TaxJurisdiction('DE-BE', 'DE-BE', 'DE', 'BE'))?->rate->percentage())->toBe('20.0000')
        ->and($resolver->resolve('reduced', new TaxJurisdiction('DE-BE', 'DE-BE', 'DE', 'BE'))?->rate->percentage())->toBe('7.0000');
});

function configuredTaxCalculator(): ConfiguredTaxCalculator
{
    return new ConfiguredTaxCalculator(
        new DestinationTaxJurisdictionResolver,
        app(TaxRateResolver::class),
    );
}

function taxContext(TaxPriceMode $priceMode, TaxableLine ...$lines): TaxCalculationContext
{
    return new TaxCalculationContext(
        lines: $lines,
        currency: Currency::EUR,
        priceMode: $priceMode,
        shippingAddress: configuredTaxAddress('DE'),
    );
}

function configuredTaxAddress(string $country): Address
{
    return new Address($country, 'Ada', 'Lovelace', 'Main Street 1', 'Berlin', '10115');
}
