<?php

use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Exceptions\Cart\CartQuantityBelowMinimumException;
use Larasell\Larasell\Exceptions\Cart\CartQuantityExceedsMaximumException;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

it('checks product min and max quantities when adding products to the cart', function (?int $minQuantity, ?int $maxQuantity, int $quantity, ?string $exceptionMessage) {
    $cart = Cart::create(['currency' => Currency::USD]);
    $product = product([
        'min_quantity' => $minQuantity,
        'max_quantity' => $maxQuantity,
    ]);

    if ($exceptionMessage !== null) {
        $exception = str_contains($exceptionMessage, 'below')
            ? CartQuantityBelowMinimumException::class
            : CartQuantityExceedsMaximumException::class;

        expect(fn () => $cart->add($product, $quantity))
            ->toThrow($exception, $exceptionMessage);

        expect($cart->items()->count())->toBe(0);

        return;
    }

    $item = $cart->add($product, $quantity);

    expect($item->quantity)->toBe($quantity)
        ->and($cart->items()->count())->toBe(1);
})->with([
    'no min or max accepts one' => [null, null, 1, null],
    'no min or max accepts high quantity' => [null, null, 50, null],
    'min only rejects below min' => [3, null, 2, 'Cart item quantity is below the product minimum quantity.'],
    'min only accepts min' => [3, null, 3, null],
    'min only accepts above min' => [3, null, 10, null],
    'max only accepts below max' => [null, 5, 4, null],
    'max only accepts max' => [null, 5, 5, null],
    'max only rejects above max' => [null, 5, 6, 'Cart item quantity exceeds the product maximum quantity.'],
    'min and max rejects below min' => [3, 5, 2, 'Cart item quantity is below the product minimum quantity.'],
    'min and max accepts min' => [3, 5, 3, null],
    'min and max accepts between min and max' => [3, 5, 4, null],
    'min and max accepts max' => [3, 5, 5, null],
    'min and max rejects above max' => [3, 5, 6, 'Cart item quantity exceeds the product maximum quantity.'],
]);

it('checks max quantity against the final quantity when adding an existing product again', function () {
    $cart = Cart::create(['currency' => Currency::USD]);
    $product = product(['max_quantity' => 5]);

    $cart->add($product, 3);

    expect(fn () => $cart->add($product, 3))
        ->toThrow(CartQuantityExceedsMaximumException::class, 'Cart item quantity exceeds the product maximum quantity.');

    expect($cart->items()->first()->quantity)->toBe(3);
});

it('allows repeated adds once the final quantity satisfies the product minimum quantity', function () {
    $cart = Cart::create(['currency' => Currency::USD]);
    $product = product(['min_quantity' => 3]);

    $cart->add($product, 3);
    $item = $cart->add($product, 1);

    expect($item->quantity)->toBe(4);
});

it('checks product min and max quantities when setting cart item quantities', function () {
    $cart = Cart::create(['currency' => Currency::USD]);
    $product = product([
        'min_quantity' => 3,
        'max_quantity' => 5,
    ]);

    expect(fn () => $cart->set($product, 2))
        ->toThrow(CartQuantityBelowMinimumException::class, 'Cart item quantity is below the product minimum quantity.');

    $item = $cart->set($product, 4);

    expect($item->quantity)->toBe(4);

    expect(fn () => $cart->set($product, 6))
        ->toThrow(CartQuantityExceedsMaximumException::class, 'Cart item quantity exceeds the product maximum quantity.');

    expect($cart->items()->first()->quantity)->toBe(4);
});

it('checks product max quantity before available stock', function () {
    $cart = Cart::create(['currency' => Currency::USD]);
    $product = product([
        'max_quantity' => 5,
        'stock' => 4,
        'allow_backorders' => false,
    ]);

    expect(fn () => $cart->add($product, 6))
        ->toThrow(CartQuantityExceedsMaximumException::class, 'Cart item quantity exceeds the product maximum quantity.');
});

function product(array $attributes = []): Product
{
    return Product::create(array_merge([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(3, true),
        'price' => Price::of(1000),
    ], $attributes));
}
