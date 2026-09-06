<?php

use Larasell\Larasell\Enums\LengthUnit;
use Larasell\Larasell\Length;

it('stores an amount with its unit', function () {
    $length = Length::of('010.00', LengthUnit::Centimeter);

    expect($length->amount())->toBe('10')
        ->and($length->unit())->toBe(LengthUnit::Centimeter)
        ->and($length->toArray())->toBe([
            'amount' => '10',
            'unit' => 'cm',
        ]);
});

it('treats equal lengths in different units as equal', function () {
    expect(Length::of(1, LengthUnit::Meter)->equals(Length::of(100, LengthUnit::Centimeter)))->toBeTrue()
        ->and(Length::of(1, LengthUnit::Inch)->equals(Length::of('25.4', LengthUnit::Millimeter)))->toBeTrue()
        ->and(Length::of(1, LengthUnit::Foot)->equals(Length::of(12, LengthUnit::Inch)))->toBeTrue()
        ->and(Length::of(1, LengthUnit::Meter)->equals(Length::of(99, LengthUnit::Centimeter)))->toBeFalse();
});

it('compares lengths across units', function () {
    $meter = Length::of(1, 'm');
    $shorter = Length::of(50, 'cm');

    expect($meter->greaterThan($shorter))->toBeTrue()
        ->and($shorter->lessThan($meter))->toBeTrue()
        ->and($meter->greaterThanOrEqual(Length::of(1000, 'mm')))->toBeTrue()
        ->and(Length::max($meter, $shorter))->toBe($meter)
        ->and(Length::min($meter, $shorter))->toBe($shorter);
});

it('converts to another unit without changing length', function () {
    $length = Length::of(1, LengthUnit::Meter)->to(LengthUnit::Centimeter);

    expect($length->amount())->toBe('100')
        ->and($length->unit())->toBe(LengthUnit::Centimeter)
        ->and($length->amount(LengthUnit::Millimeter))->toBe('1000');
});

it('adds, subtracts, and multiplies while keeping the left-hand unit', function () {
    $length = Length::of(50, LengthUnit::Centimeter)->add(Length::of(1, LengthUnit::Meter));

    expect($length->amount())->toBe('150')
        ->and($length->unit())->toBe(LengthUnit::Centimeter)
        ->and($length->subtract(Length::of(25, LengthUnit::Centimeter))->amount())->toBe('125')
        ->and(Length::of(25, LengthUnit::Centimeter)->multiply(4)->equals(Length::of(1, LengthUnit::Meter)))->toBeTrue();
});

it('rejects negative amounts and unknown units', function () {
    expect(fn () => Length::of(-1, LengthUnit::Millimeter))
        ->toThrow(InvalidArgumentException::class, 'Measurement amounts cannot be negative.')
        ->and(fn () => Length::of(1, 'yd'))
        ->toThrow(InvalidArgumentException::class, 'Unknown length unit [yd].');
});
