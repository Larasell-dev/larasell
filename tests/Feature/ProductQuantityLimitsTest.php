<?php

use InvalidArgumentException;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

it('accepts nullable min and max quantities', function () {
    $product = makeProduct([
        'min_quantity' => null,
        'max_quantity' => null,
    ]);

    expect($product->min_quantity)->toBeNull()
        ->and($product->max_quantity)->toBeNull();
});

it('accepts valid min and max quantity combinations', function (?int $minQuantity, ?int $maxQuantity) {
    $product = makeProduct([
        'min_quantity' => $minQuantity,
        'max_quantity' => $maxQuantity,
    ]);

    expect($product->min_quantity)->toBe($minQuantity)
        ->and($product->max_quantity)->toBe($maxQuantity);
})->with([
    'min only' => [2, null],
    'max only' => [null, 5],
    'min equals max' => [3, 3],
    'min lower than max' => [3, 5],
]);

it('casts persisted min and max quantities to integers', function () {
    $product = makeProduct([
        'min_quantity' => '2',
        'max_quantity' => '5',
    ])->fresh();

    expect($product->min_quantity)->toBe(2)
        ->and($product->max_quantity)->toBe(5);
});

it('rejects min quantities below one', function (?int $minQuantity) {
    expect(fn () => makeProduct(['min_quantity' => $minQuantity]))
        ->toThrow(InvalidArgumentException::class, 'Product min quantity must be at least 1.');
})->with([
    'zero' => [0],
    'negative' => [-1],
]);

it('rejects max quantities below one', function (?int $maxQuantity) {
    expect(fn () => makeProduct(['max_quantity' => $maxQuantity]))
        ->toThrow(InvalidArgumentException::class, 'Product max quantity must be at least 1.');
})->with([
    'zero' => [0],
    'negative' => [-1],
]);

it('rejects a min quantity higher than the current max quantity', function () {
    $product = makeProduct(['max_quantity' => 5]);

    expect(fn () => $product->min_quantity = 6)
        ->toThrow(InvalidArgumentException::class, 'Product min quantity cannot exceed max quantity.');
});

it('rejects a max quantity lower than the current min quantity', function () {
    $product = makeProduct(['min_quantity' => 3]);

    expect(fn () => $product->max_quantity = 2)
        ->toThrow(InvalidArgumentException::class, 'Product max quantity cannot be lower than min quantity.');
});

it('keeps the previous min quantity when assigning an invalid min quantity', function () {
    $product = makeProduct([
        'min_quantity' => 3,
        'max_quantity' => 5,
    ]);

    expect(fn () => $product->min_quantity = 6)
        ->toThrow(InvalidArgumentException::class);

    expect($product->min_quantity)->toBe(3)
        ->and($product->fresh()->min_quantity)->toBe(3);
});

it('keeps the previous max quantity when assigning an invalid max quantity', function () {
    $product = makeProduct([
        'min_quantity' => 3,
        'max_quantity' => 5,
    ]);

    expect(fn () => $product->max_quantity = 2)
        ->toThrow(InvalidArgumentException::class);

    expect($product->max_quantity)->toBe(5)
        ->and($product->fresh()->max_quantity)->toBe(5);
});

it('allows clearing min and max quantity limits', function () {
    $product = makeProduct([
        'min_quantity' => 3,
        'max_quantity' => 5,
    ]);

    $product->min_quantity = null;
    $product->max_quantity = null;
    $product->save();

    $product = $product->fresh();

    expect($product->min_quantity)->toBeNull()
        ->and($product->max_quantity)->toBeNull();
});

function makeProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(3, true),
        'price' => Price::of(1000, Currency::USD),
    ], $attributes));
}
