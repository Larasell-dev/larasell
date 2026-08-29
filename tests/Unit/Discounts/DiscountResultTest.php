<?php

use Larasell\Larasell\Discounts\DiscountAllocation;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Price;

it('describes a discount and totals its allocations', function () {
    $result = new DiscountResult(
        identifier: 'summer-sale',
        name: 'Summer sale',
        allocations: [
            new DiscountAllocation('line:1', Price::of(600)),
            new DiscountAllocation('line:2', Price::of(400)),
        ],
    );

    expect($result->identifier)->toBe('summer-sale')
        ->and($result->name)->toBe('Summer sale')
        ->and($result->allocations)->toHaveCount(2)
        ->and($result->total()->amount())->toBe('1000');
});

it('requires a discount identifier and name', function (string $identifier, string $name, string $message) {
    expect(fn () => new DiscountResult($identifier, $name, []))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'missing identifier' => ['', 'Summer sale', 'A discount identifier is required.'],
    'missing name' => ['summer-sale', '', 'A discount name is required.'],
]);

it('only accepts discount allocations', function () {
    expect(fn () => new DiscountResult('summer-sale', 'Summer sale', [Price::of(100)]))
        ->toThrow(InvalidArgumentException::class, 'Discount allocations must be DiscountAllocation instances.');
});

it('only accepts one allocation per target', function () {
    expect(fn () => new DiscountResult('summer-sale', 'Summer sale', [
        new DiscountAllocation('line:1', Price::of(100)),
        new DiscountAllocation('line:1', Price::of(200)),
    ]))->toThrow(InvalidArgumentException::class, 'A discount result may only contain one allocation per target.');
});

it('requires a target and a positive allocation amount', function (string $target, Price $amount, string $message) {
    expect(fn () => new DiscountAllocation($target, $amount))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'missing target' => ['', Price::of(100), 'A discount allocation target is required.'],
    'zero amount' => ['line:1', Price::of(0), 'A discount allocation amount must be positive.'],
    'negative amount' => ['line:1', Price::of(-1), 'A discount allocation amount must be positive.'],
]);
