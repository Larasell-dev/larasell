<?php

use Larasell\Larasell\Enums\Country;
use Larasell\Larasell\Exceptions\InvalidVatId;
use Larasell\Larasell\VatId;

it('accepts the formats of EU countries and Northern Ireland', function (string $input) {
    expect(VatId::of($input)->value())->toBe($input);
})->with([
    'Germany' => ['DE123456789'],
    'Austria' => ['ATU12345678'],
    'Netherlands' => ['NL123456789B01'],
    'France with letters' => ['FRXX123456789'],
    'Spain with a letter first' => ['ESX1234567X'],
    'Ireland' => ['IE1234567WA'],
    'Belgium' => ['BE0123456789'],
    'Greece' => ['EL123456789'],
    'Sweden' => ['SE123456789001'],
    'Northern Ireland' => ['XI123456789'],
]);

it('removes spaces, dots and dashes and uppercases the VAT ID', function () {
    expect(VatId::of(' de 123.456-789 ')->value())->toBe('DE123456789')
        ->and((string) VatId::of('atu12345678'))->toBe('ATU12345678');
});

it('knows the country of the VAT ID, also where its prefix differs', function (string $input, Country $country) {
    expect(VatId::of($input)->country())->toBe($country);
})->with([
    'Germany' => ['DE123456789', Country::DE],
    'Greece uses EL' => ['EL123456789', Country::GR],
    'Northern Ireland uses XI' => ['XI123456789', Country::GB],
]);

it('rejects what does not match the country format', function (string $input) {
    expect(fn () => VatId::of($input))->toThrow(InvalidVatId::class);
})->with([
    'empty' => ['  '],
    'too short' => ['DE12345678'],
    'too long' => ['DE1234567890'],
    'letter where digits go' => ['DE12345678X'],
    'Austria without U' => ['AT12345678'],
    'Greece with its ISO code' => ['GR123456789'],
    'country outside the EU' => ['US123456789'],
    'no country' => ['123456789'],
    'Swedish without 01' => ['SE123456789002'],
]);

it('compares VAT IDs by their normalised value', function () {
    expect(VatId::of('DE 123456789')->equals(VatId::of('de123456789')))->toBeTrue();
});
