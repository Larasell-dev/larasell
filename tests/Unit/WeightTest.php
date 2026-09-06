<?php

use Larasell\Larasell\Enums\WeightUnit;
use Larasell\Larasell\Weight;

it('stores an amount with its unit', function () {
    $weight = Weight::of('001.50', WeightUnit::Kilogram);

    expect($weight->amount())->toBe('1.5')
        ->and($weight->unit())->toBe(WeightUnit::Kilogram)
        ->and($weight->toArray())->toBe([
            'amount' => '1.5',
            'unit' => 'kg',
        ]);
});

it('treats equal masses in different units as equal', function () {
    expect(Weight::of(1, WeightUnit::Kilogram)->equals(Weight::of(1000, WeightUnit::Gram)))->toBeTrue()
        ->and(Weight::of(16, WeightUnit::Ounce)->equals(Weight::of(1, WeightUnit::Pound)))->toBeTrue()
        ->and(Weight::of(1, WeightUnit::Kilogram)->equals(Weight::of(999, WeightUnit::Gram)))->toBeFalse();
});

it('compares masses across units', function () {
    $kilogram = Weight::of(1, 'kg');
    $half = Weight::of(500, 'g');
    $same = Weight::of(1000, 'g');

    expect($kilogram->greaterThan($half))->toBeTrue()
        ->and($half->lessThan($kilogram))->toBeTrue()
        ->and($kilogram->greaterThanOrEqual($same))->toBeTrue()
        ->and($half->lessThanOrEqual($kilogram))->toBeTrue()
        ->and($same->greaterThan($kilogram))->toBeFalse()
        ->and(Weight::max($kilogram, $half))->toBe($kilogram)
        ->and(Weight::min($kilogram, $half))->toBe($half);
});

it('converts to another unit without changing mass', function () {
    $weight = Weight::of(1, WeightUnit::Kilogram)->to(WeightUnit::Gram);

    expect($weight->amount())->toBe('1000')
        ->and($weight->unit())->toBe(WeightUnit::Gram)
        ->and($weight->equals(Weight::of(1, WeightUnit::Kilogram)))->toBeTrue()
        ->and($weight->amount(WeightUnit::Kilogram))->toBe('1');
});

it('adds, subtracts, and multiplies while keeping the left-hand unit', function () {
    $weight = Weight::of(500, WeightUnit::Gram)->add(Weight::of(1, WeightUnit::Kilogram));

    expect($weight->amount())->toBe('1500')
        ->and($weight->unit())->toBe(WeightUnit::Gram)
        ->and($weight->subtract(Weight::of(250, WeightUnit::Gram))->amount())->toBe('1250')
        ->and(Weight::of(250, WeightUnit::Gram)->multiply(4)->equals(Weight::of(1, WeightUnit::Kilogram)))->toBeTrue();
});

it('identifies zero and positive weights', function () {
    expect(Weight::of(0, WeightUnit::Gram)->isZero())->toBeTrue()
        ->and(Weight::of(0, WeightUnit::Gram)->isPositive())->toBeFalse()
        ->and(Weight::of(1, WeightUnit::Gram)->isPositive())->toBeTrue();
});

it('rejects negative amounts, unknown units, and negative results', function () {
    expect(fn () => Weight::of(-1, WeightUnit::Gram))
        ->toThrow(InvalidArgumentException::class, 'Measurement amounts cannot be negative.')
        ->and(fn () => Weight::of(1, 'stone'))
        ->toThrow(InvalidArgumentException::class, 'Unknown weight unit [stone].')
        ->and(fn () => Weight::of(1, WeightUnit::Gram)->subtract(Weight::of(2, WeightUnit::Gram)))
        ->toThrow(InvalidArgumentException::class, 'Measurement amounts cannot be negative.')
        ->and(fn () => Weight::of(1, WeightUnit::Gram)->multiply(-1))
        ->toThrow(InvalidArgumentException::class, 'Measurement multipliers cannot be negative.');
});
