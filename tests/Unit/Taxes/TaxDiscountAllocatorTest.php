<?php

use Larasell\Larasell\Discounts\ProportionalDiscountAllocator;
use Larasell\Larasell\Enums\TaxableLineType;
use Larasell\Larasell\Price;
use Larasell\Larasell\Taxes\TaxableLine;
use Larasell\Larasell\Taxes\TaxDiscountAllocator;

it('allocates a controlling discount by largest remainder with stable line identifiers', function () {
    $allocator = new TaxDiscountAllocator(new ProportionalDiscountAllocator);
    $lines = [
        new TaxableLine('line:c', Price::of(1), 'standard'),
        new TaxableLine('line:a', Price::of(1), 'standard'),
        new TaxableLine('line:b', Price::of(1), 'standard'),
    ];

    $allocated = $allocator->allocate(Price::of(2), $lines);

    expect(collect($allocated)->mapWithKeys(
        fn (TaxableLine $line): array => [$line->identifier => $line->discountAmount->amount()],
    )->all())->toBe([
        'line:c' => '0',
        'line:a' => '1',
        'line:b' => '1',
    ]);
});

it('adds an order discount to existing line discounts without exceeding line amounts', function () {
    $allocator = new TaxDiscountAllocator(new ProportionalDiscountAllocator);
    $lines = [
        new TaxableLine('line:1', Price::of(1000), 'standard', discountAmount: Price::of(100)),
        new TaxableLine('line:2', Price::of(1000), 'standard'),
    ];

    $allocated = $allocator->allocate(Price::of(1900), $lines);

    expect($allocated[0]->discountAmount->amount())->toBe('1000')
        ->and($allocated[1]->discountAmount->amount())->toBe('1000')
        ->and($allocated[0]->discountedAmount()->amount())->toBe('0')
        ->and($allocated[1]->discountedAmount()->amount())->toBe('0');
});

it('constructs shipping as a distinct taxable line', function () {
    $line = TaxableLine::shipping('shipping', Price::of(499));

    expect($line->type)->toBe(TaxableLineType::Shipping)
        ->and($line->category)->toBe('shipping');
});

it('rejects a line discount larger than its amount', function () {
    new TaxableLine('line', Price::of(100), 'standard', discountAmount: Price::of(101));
})->throws(InvalidArgumentException::class, 'A taxable line discount must be between zero and the line amount.');
