<?php

use Larasell\Larasell\Address;
use Larasell\Larasell\Contracts\TaxCalculator;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\TaxResultStatus;
use Larasell\Larasell\Enums\TaxTreatment;
use Larasell\Larasell\Price;
use Larasell\Larasell\Taxes\NoTaxCalculator;
use Larasell\Larasell\Taxes\TaxableLine;
use Larasell\Larasell\Taxes\TaxCalculationContext;
use Larasell\Larasell\Taxes\TaxComponent;
use Larasell\Larasell\Taxes\TaxJurisdiction;
use Larasell\Larasell\Taxes\TaxJurisdictionResolution;
use Larasell\Larasell\Taxes\TaxLineResult;
use Larasell\Larasell\Taxes\TaxRate;
use Larasell\Larasell\Taxes\TaxResult;

it('normalizes decimal string tax rates without floating point arithmetic', function () {
    expect(TaxRate::from('19')->percentage())->toBe('19.0000')
        ->and(TaxRate::from('7.5')->percentage())->toBe('7.5000')
        ->and(TaxRate::from('0.0001')->toArray())->toBe(['percentage' => '0.0001']);
});

it('rejects invalid tax rates', function (string $rate) {
    TaxRate::from($rate);
})->with(['19.00000', '-1', '100.0001', '1e1', ' 19'])->throws(InvalidArgumentException::class);

it('holds all location inputs without deciding which one is authoritative', function () {
    $shipping = taxAddress('DE');
    $billing = taxAddress('FR');
    $origin = taxAddress('NL');

    $context = new TaxCalculationContext(
        lines: [new TaxableLine('product:1', Price::of(1190), 'standard', 2)],
        currency: Currency::EUR,
        priceMode: TaxPriceMode::Inclusive,
        shippingAddress: $shipping,
        billingAddress: $billing,
        originAddress: $origin,
        transactionIdentifier: 'cart:1',
    );

    expect($context->shippingAddress)->toBe($shipping)
        ->and($context->billingAddress)->toBe($billing)
        ->and($context->originAddress)->toBe($origin)
        ->and($context->lines[0]->amount->amount())->toBe('1190');
});

it('requires unique calculation line identifiers', function () {
    new TaxCalculationContext(
        lines: [
            new TaxableLine('line:1', Price::of(100), 'standard'),
            new TaxableLine('line:1', Price::of(200), 'reduced'),
        ],
        currency: Currency::EUR,
        priceMode: TaxPriceMode::Exclusive,
    );
})->throws(InvalidArgumentException::class, 'Tax calculation line identifiers must be unique.');

it('supports multiple components whose amounts equal the line tax', function () {
    $jurisdiction = new TaxJurisdiction('us-ca-la', 'Los Angeles, CA', 'US', state: 'CA', county: 'Los Angeles');
    $line = new TaxLineResult(
        lineIdentifier: 'product:1',
        category: 'standard',
        treatment: TaxTreatment::Taxable,
        taxableAmount: Price::of(10000),
        taxAmount: Price::of(950),
        components: [
            new TaxComponent('state', 'California state tax', TaxRate::from('7.25'), Price::of(725), $jurisdiction),
            new TaxComponent('county', 'Los Angeles county tax', TaxRate::from('2.25'), Price::of(225), $jurisdiction),
        ],
    );

    $result = TaxResult::calculated(TaxPriceMode::Exclusive, [$line], $jurisdiction);

    expect($result->status)->toBe(TaxResultStatus::Calculated)
        ->and($result->taxableAmount()->amount())->toBe('10000')
        ->and($result->taxAmount()->amount())->toBe('950');
});

it('rejects component amounts that do not equal the line tax', function () {
    $jurisdiction = new TaxJurisdiction('de', 'Germany', 'DE');

    new TaxLineResult(
        'product:1',
        'standard',
        TaxTreatment::Taxable,
        Price::of(1000),
        Price::of(190),
        [new TaxComponent('vat', 'VAT', TaxRate::from('19'), Price::of(189), $jurisdiction)],
    );
})->throws(InvalidArgumentException::class, 'Tax component amounts must equal the line tax amount.');

it('represents provisional and unavailable outcomes explicitly', function () {
    $jurisdiction = new TaxJurisdiction('de', 'Germany', 'DE');
    $provisional = TaxJurisdictionResolution::provisional($jurisdiction, 'Billing address fallback was used.');
    $unavailable = TaxResult::unavailable(TaxPriceMode::Exclusive, 'A destination address is required.');

    expect($provisional->status)->toBe(TaxResultStatus::Provisional)
        ->and($provisional->jurisdiction)->toBe($jurisdiction)
        ->and($unavailable->status)->toBe(TaxResultStatus::Unavailable)
        ->and($unavailable->lines)->toBe([])
        ->and($unavailable->reason)->toBe('A destination address is required.');
});

it('uses an explicit no-tax calculator by default', function () {
    $calculator = app(TaxCalculator::class);
    $context = new TaxCalculationContext(
        lines: [
            new TaxableLine('product:1', Price::of(1000), 'standard'),
            new TaxableLine('shipping', Price::of(500), 'shipping'),
        ],
        currency: Currency::EUR,
        priceMode: TaxPriceMode::Exclusive,
    );

    $result = $calculator->calculate($context);

    expect($calculator)->toBeInstanceOf(NoTaxCalculator::class)
        ->and($result->status)->toBe(TaxResultStatus::Calculated)
        ->and($result->taxAmount()->amount())->toBe('0')
        ->and($result->taxableAmount()->amount())->toBe('0')
        ->and($result->lines)->toHaveCount(2)
        ->and($result->lines[0]->treatment)->toBe(TaxTreatment::NotTaxable)
        ->and($result->metadata)->toBe(['calculator' => 'none']);
});

function taxAddress(string $country): Address
{
    return new Address(
        country: $country,
        firstName: 'Ada',
        lastName: 'Lovelace',
        street: '1 Main Street',
        city: 'Berlin',
        postcode: '10115',
    );
}
