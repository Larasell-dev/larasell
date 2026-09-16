<?php

use Larasell\Larasell\Barcode;

it('accepts ean-8, upc-a, ean-13, and gtin-14 barcodes', function (string $value) {
    expect(Barcode::of($value)->value())->toBe(preg_replace('/[\s-]/', '', $value));
})->with([
    '96385074',
    '012345678905',
    '4006381333931',
    '04012345678901',
    '400 6381-333931',
]);

it('rejects barcodes that are not a supported gtin', function (string $value) {
    Barcode::of($value);
})->with([
    '',
    'BIC-001',
    '123',
    '00001',
    '4006381333932',
])->throws(InvalidArgumentException::class);

it('compares barcodes by their digit value', function () {
    expect(Barcode::of('4006381333931')->equals(Barcode::of('400-6381-333931')))->toBeTrue()
        ->and(Barcode::of('012345678905')->equals(Barcode::of('4006381333931')))->toBeFalse();
});
