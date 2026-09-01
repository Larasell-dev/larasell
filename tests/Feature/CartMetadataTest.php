<?php

use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

it('stores and manages cart metadata', function () {
    $cart = Cart::query()->create(['currency' => Currency::EUR]);

    expect($cart->metadata)->toBeEmpty()
        ->and($cart->metadata->get('missing', 'fallback'))->toBe('fallback');

    $cart->metadata
        ->put('delivery_instructions', 'Leave at reception')
        ->put('gift', true);
    $cart->save();

    expect($cart->fresh()->metadata->all())->toEqual([
        'delivery_instructions' => 'Leave at reception',
        'gift' => true,
    ])->and($cart->metadata->get('delivery_instructions'))->toBe('Leave at reception');

    $cart->metadata->forget('gift');
    $cart->save();

    expect($cart->fresh()->metadata->all())->toBe([
        'delivery_instructions' => 'Leave at reception',
    ]);

    $cart->metadata = collect(['table_number' => 12]);
    $cart->save();

    expect($cart->fresh()->metadata->all())->toBe(['table_number' => 12]);
});

it('snapshots cart metadata on the order', function () {
    $product = Product::query()->create([
        'slug' => 'metadata-product',
        'name' => 'Metadata product',
        'price' => Price::of(1000),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create([
        'currency' => Currency::EUR,
        'metadata' => ['delivery_instructions' => 'Ring the bell'],
    ]);
    $cart->add($product);

    $order = app(Checkout::class)->create($cart, cartMetadataCheckoutData())->order;
    $cart->metadata->put('delivery_instructions', 'Changed later');
    $cart->save();

    expect($order->fresh()->metadata->all())->toBe([
        'delivery_instructions' => 'Ring the bell',
    ]);
});

it('stores metadata on cart items and uses it as part of line identity', function () {
    $product = Product::query()->create([
        'slug' => 'customizable-product',
        'name' => 'Customizable product',
        'price' => Price::of(1000),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);

    $alice = $cart->add($product, 1, [
        'belongs_to' => 'Alice',
        'customizations' => ['without' => ['onions']],
    ]);
    $cart->add($product, 2, [
        'customizations' => ['without' => ['onions']],
        'belongs_to' => 'Alice',
    ]);
    $bob = $cart->add($product, 1, [
        'belongs_to' => 'Bob',
        'customizations' => ['extra' => ['cheese']],
    ]);

    expect($cart->items()->count())->toBe(2)
        ->and($alice->fresh()->quantity)->toBe(3)
        ->and($bob->metadata->all())->toBe([
            'belongs_to' => 'Bob',
            'customizations' => ['extra' => ['cheese']],
        ]);
});

it('snapshots cart item metadata on the order item', function () {
    $product = Product::query()->create([
        'slug' => 'line-metadata-product',
        'name' => 'Line metadata product',
        'price' => Price::of(1000),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $item = $cart->add($product, metadata: ['belongs_to' => 'Alice']);

    $order = app(Checkout::class)->create($cart, cartMetadataCheckoutData())->order;
    $item->metadata->put('belongs_to', 'Changed later');
    $item->save();

    expect($order->items()->sole()->metadata->all())->toBe(['belongs_to' => 'Alice']);
});

it('enforces variant stock across customized lines', function () {
    $product = Product::query()->create([
        'slug' => 'limited-customizable-product',
        'name' => 'Limited customizable product',
        'price' => Price::of(1000),
        'stock' => 3,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product, 2, ['belongs_to' => 'Alice']);

    expect(fn () => $cart->add($product, 2, ['belongs_to' => 'Bob']))
        ->toThrow(InvalidArgumentException::class, 'Cart item quantity exceeds available product stock.');
});

/** @return array<string, mixed> */
function cartMetadataCheckoutData(): array
{
    return [
        'customer_email' => 'metadata@example.com',
        'customer_name' => 'Metadata Customer',
        'billing_address' => null,
        'shipping_address' => null,
    ];
}
