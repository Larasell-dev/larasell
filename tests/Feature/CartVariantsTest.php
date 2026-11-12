<?php

use Larasell\Larasell\Carts\CartItemId;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductVariant;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\VariantId;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Exceptions\Cart\CartQuantityBelowMinimumException;
use Larasell\Larasell\Exceptions\Cart\CartQuantityExceedsMaximumException;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Price;

it('creates an authoritative default variant for a simple product', function () {
    $product = cartVariantProduct([
        'price' => Price::of(1299),
        'stock' => 8,
        'allow_backorders' => false,
        'min_quantity' => 2,
        'max_quantity' => 6,
    ]);

    $variant = $product->defaultVariant();
    $variant->update(['sku' => 'SIMPLE-SKU', 'barcode' => '0012345678905']);

    expect($product->variants)->toHaveCount(1)
        ->and($variant->is_default)->toBeTrue()
        ->and($variant->sku)->toBe('SIMPLE-SKU')
        ->and($variant->barcode)->toBe('0012345678905')
        ->and($variant->price->amount())->toBe('1299')
        ->and($variant->stock)->toBe(8)
        ->and($variant->allow_backorders)->toBeFalse()
        ->and($variant->min_quantity)->toBe(2)
        ->and($variant->max_quantity)->toBe(6);
});

it('keeps the simple product cart api through its default variant', function () {
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);
    $product = cartVariantProduct();

    $item = $cart->add($product, 2);

    expect($item->product->is($product))->toBeTrue()
        ->and($item->variant->is($product->defaultVariant()))->toBeTrue()
        ->and($item->quantity)->toBe(2);
});

it('adds a product by its id', function () {
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);
    $product = cartVariantProduct();

    $item = $cart->add(ProductId::of($product), 2);

    expect($item->product->is($product))->toBeTrue()
        ->and($item->quantity)->toBe(2);
});

it('adds a variant by its id', function () {
    [, , $medium] = cartVariantCatalog();
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);

    $item = $cart->add(VariantId::of($medium), 2);

    expect($item->variant->is($medium))->toBeTrue()
        ->and($item->quantity)->toBe(2);
});

it('does not take a bare number, which could be a product or a cart line', function (string $method) {
    [, $variant] = cartVariantCatalog();
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);
    $item = $cart->add($variant);

    match ($method) {
        'add' => $cart->add($item->getKey()),
        'set' => $cart->set($item->getKey(), 1),
        'remove' => $cart->remove($item->getKey()),
    };
})->with(['add', 'set', 'remove'])->throws(TypeError::class);

it('does not add a cart line id as if it were a product', function () {
    [, $variant] = cartVariantCatalog();
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);
    $item = $cart->add($variant);

    $cart->add(CartItemId::of($item));
})->throws(TypeError::class);

it('stores two variants of one product as separate cart lines', function () {
    [$product, $small, $medium] = cartVariantCatalog();
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);

    $smallItem = $cart->add($small);
    $mediumItem = $cart->add($medium, 2);

    expect($cart->items)->toHaveCount(2)
        ->and($smallItem->product->is($product))->toBeTrue()
        ->and($smallItem->variant->is($small))->toBeTrue()
        ->and($mediumItem->variant->is($medium))->toBeTrue();
});

it('merges repeated additions of the same variant', function () {
    [, $small] = cartVariantCatalog();
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);

    $cart->add($small, 2);
    $item = $cart->add($small, 3);

    expect($cart->items()->count())->toBe(1)
        ->and($item->quantity)->toBe(5);
});

it('sets and removes cart lines by variant identity', function () {
    [, $small, $medium] = cartVariantCatalog();
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);
    $cart->add($small);
    $cart->add($medium);

    $item = $cart->set($small, 4);
    $cart->remove($medium);

    expect($item->quantity)->toBe(4)
        ->and($cart->items)->toHaveCount(1)
        ->and($cart->items->first()->variant->is($small))->toBeTrue();
});

it('removes a cart line by its id', function () {
    [, $variant] = cartVariantCatalog();
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);
    $otherCart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);
    $item = $cart->add($variant);
    $otherItem = $otherCart->add($variant);

    $cart->remove(CartItemId::of($item));
    $cart->remove(CartItemId::of($otherItem));

    expect($cart->items()->count())->toBe(0)
        ->and($otherCart->items()->count())->toBe(1);
});

it('sets a cart line quantity by its id', function () {
    [, $variant] = cartVariantCatalog();
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);
    $item = $cart->add($variant, metadata: ['engraving' => 'Hello']);

    $updatedItem = $cart->set(CartItemId::of($item), 3);

    expect($updatedItem->is($item))->toBeTrue()
        ->and($updatedItem->quantity)->toBe(3)
        ->and($updatedItem->metadata->all())->toBe(['engraving' => 'Hello']);
});

it('resolves commerce values from the selected variant', function () {
    [, $variant] = cartVariantCatalog([
        'sku' => 'VARIANT-SKU',
        'barcode' => '0098765432106',
        'price' => Price::of(1750),
        'stock' => 3,
        'allow_backorders' => false,
        'min_quantity' => 2,
        'max_quantity' => 3,
    ]);
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);

    expect(fn () => $cart->add($variant, 1))
        ->toThrow(CartQuantityBelowMinimumException::class, 'Cart item quantity is below the variant minimum quantity.');

    $item = $cart->add($variant, 2);

    expect($item->unitPrice()->amount())->toBe('1750')
        ->and($item->total()->amount())->toBe('3500')
        ->and($item->sku())->toBe('VARIANT-SKU')
        ->and($item->barcode())->toBe('0098765432106')
        ->and($item->availableStock())->toBe(3)
        ->and($item->allowsBackorders())->toBeFalse();

    expect(fn () => $cart->add($variant, 2))
        ->toThrow(CartQuantityExceedsMaximumException::class, 'Cart item quantity exceeds the variant maximum quantity.');
});

it('inherits nullable variant commerce values from the product, but not identifiers', function () {
    [$product, $variant] = cartVariantCatalog();
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::USD]);
    $item = $cart->add($variant);

    expect($item->unitPrice()->amount())->toBe($product->price->amount())
        ->and($item->sku())->toBeNull()
        ->and($item->barcode())->toBeNull()
        ->and($item->availableStock())->toBe($product->stock)
        ->and($item->allowsBackorders())->toBe($product->allow_backorders);
});

function cartVariantProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'name' => fake()->words(3, true),
        'price' => Price::of(1000),
        'stock' => 10,
        'allow_backorders' => true,
    ], $attributes));
}

/** @return array{Product, ProductVariant, ProductVariant} */
function cartVariantCatalog(array $firstVariantAttributes = []): array
{
    $product = cartVariantProduct();
    // The first combination is the product's default variant, which mirrors the product.
    [, $first, $second] = optionVariants($product, [optionDefinition('size', ['xs', 'small', 'medium'])]);
    $first->update($firstVariantAttributes);

    return [$product, $first->refresh(), $second];
}
