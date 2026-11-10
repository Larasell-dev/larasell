<?php

use Larasell\Larasell\Customers\CustomerGroupCode;
use Larasell\Larasell\Customers\CustomerGroupDescription;
use Larasell\Larasell\Customers\Exceptions\InvalidCustomerGroupCode;
use Larasell\Larasell\Customers\Exceptions\InvalidCustomerGroupDescription;

it('accepts lowercase codes joined by - or _ up to 64 characters and trims them', function (string $input, string $expected) {
    expect(CustomerGroupCode::of($input)->value())->toBe($expected);
})->with([
    'plain' => ['wholesale', 'wholesale'],
    'joined' => ['b2b-premium_eu', 'b2b-premium_eu'],
    'padded' => ['  vip ', 'vip'],
    '64 characters' => [str_repeat('a', 64), str_repeat('a', 64)],
]);

it('rejects codes other modules could not refer to', function (string $input) {
    expect(fn () => CustomerGroupCode::of($input))->toThrow(InvalidCustomerGroupCode::class);
})->with([
    'empty' => ['  '],
    'uppercase' => ['Wholesale'],
    'space' => ['whole sale'],
    'leading dash' => ['-wholesale'],
    'double dash' => ['whole--sale'],
    '65 characters' => [str_repeat('a', 65)],
]);

it('compares codes by value', function () {
    expect(CustomerGroupCode::of('vip')->equals(CustomerGroupCode::of(' vip')))->toBeTrue()
        ->and(CustomerGroupCode::of('vip')->equals(CustomerGroupCode::of('retail')))->toBeFalse();
});

it('trims descriptions and allows up to 2000 characters', function () {
    expect(CustomerGroupDescription::of("  Resellers\n")->value())->toBe('Resellers')
        ->and(CustomerGroupDescription::of(str_repeat('a', 2000))->value())->toHaveLength(2000);
});

it('rejects empty and too long descriptions', function (string $input) {
    expect(fn () => CustomerGroupDescription::of($input))->toThrow(InvalidCustomerGroupDescription::class);
})->with([
    'blank' => [" \n "],
    '2001 characters' => [str_repeat('a', 2001)],
]);
