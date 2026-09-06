<?php

use Larasell\Larasell\Dimensions;
use Larasell\Larasell\Enums\LengthUnit;
use Larasell\Larasell\Length;

it('stores three axes with a shared unit', function () {
    $dimensions = Dimensions::of(10, 5, 2, LengthUnit::Centimeter);

    expect($dimensions->length()->amount())->toBe('10')
        ->and($dimensions->width()->amount())->toBe('5')
        ->and($dimensions->height()->amount())->toBe('2')
        ->and($dimensions->unit())->toBe(LengthUnit::Centimeter)
        ->and($dimensions->toArray())->toBe([
            'length' => '10',
            'width' => '5',
            'height' => '2',
            'unit' => 'cm',
        ]);
});

it('treats equal boxes in different units as equal', function () {
    $centimeters = Dimensions::of(10, 5, 2, LengthUnit::Centimeter);
    $millimeters = Dimensions::of(100, 50, 20, LengthUnit::Millimeter);

    expect($centimeters->equals($millimeters))->toBeTrue()
        ->and($centimeters->equals(Dimensions::of(2, 5, 10, LengthUnit::Centimeter)))->toBeFalse();
});

it('compares volume independently of axis order', function () {
    $box = Dimensions::of(10, 5, 2, LengthUnit::Centimeter);
    $rotated = Dimensions::of(2, 10, 5, LengthUnit::Centimeter);
    $smaller = Dimensions::of(4, 4, 4, LengthUnit::Centimeter);

    expect($box->hasEqualVolume($rotated))->toBeTrue()
        ->and($box->hasGreaterVolumeThan($smaller))->toBeTrue()
        ->and($smaller->hasLesserVolumeThan($box))->toBeTrue()
        ->and($box->volume())->toBe('100')
        ->and($box->volume(LengthUnit::Millimeter))->toBe('100000');
});

it('exposes the longest and shortest sides', function () {
    $dimensions = Dimensions::of(10, 5, 2, LengthUnit::Centimeter);

    expect($dimensions->longestSide()->equals(Length::of(10, LengthUnit::Centimeter)))->toBeTrue()
        ->and($dimensions->shortestSide()->equals(Length::of(2, LengthUnit::Centimeter)))->toBeTrue();
});

it('checks whether a box fits inside another, rotating by default', function () {
    $item = Dimensions::of(10, 2, 5, LengthUnit::Centimeter);
    $box = Dimensions::of(6, 6, 11, LengthUnit::Centimeter);
    $tooSmall = Dimensions::of(9, 4, 4, LengthUnit::Centimeter);

    expect($item->fitsInside($box))->toBeTrue()
        ->and($item->fitsInside($box, allowRotation: false))->toBeFalse()
        ->and($item->fitsInside($tooSmall))->toBeFalse();
});

it('converts every axis to another unit', function () {
    $dimensions = Dimensions::of(1, 2, '0.5', LengthUnit::Meter)->to(LengthUnit::Centimeter);

    expect($dimensions->length()->amount())->toBe('100')
        ->and($dimensions->width()->amount())->toBe('200')
        ->and($dimensions->height()->amount())->toBe('50')
        ->and($dimensions->unit())->toBe(LengthUnit::Centimeter);
});

it('rejects mixed units on the constructor', function () {
    expect(fn () => new Dimensions(
        Length::of(10, LengthUnit::Centimeter),
        Length::of(5, LengthUnit::Inch),
        Length::of(2, LengthUnit::Centimeter),
    ))->toThrow(InvalidArgumentException::class, 'Dimension axes must use the same unit.');
});
