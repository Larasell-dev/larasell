<?php

use InvalidArgumentException;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Models\ProductAttributeValue;
use Larasell\Larasell\Models\ProductVariant;
use Larasell\Larasell\Price;

it('creates an authoritative default variant for a simple product', function () {
    $product = cartVariantProduct([
        'sku' => 'SIMPLE-SKU',
        'barcode' => '0012345678905',
        'price' => Price::of(1299),
        'stock' => 8,
        'allow_backorders' => false,
        'min_quantity' => 2,
        'max_quantity' => 6,
    ]);

    $variant = $product->defaultVariant();

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
    $cart = Cart::create(['currency' => Currency::USD]);
    $product = cartVariantProduct();

    $item = $cart->add($product, 2);

    expect($item->product->is($product))->toBeTrue()
        ->and($item->variant->is($product->defaultVariant()))->toBeTrue()
        ->and($item->quantity)->toBe(2);
});

it('stores two variants of one product as separate cart lines', function () {
    [$product, $small, $medium] = cartVariantCatalog();
    $cart = Cart::create(['currency' => Currency::USD]);

    $smallItem = $cart->add($small);
    $mediumItem = $cart->add($medium, 2);

    expect($cart->items)->toHaveCount(2)
        ->and($smallItem->product->is($product))->toBeTrue()
        ->and($smallItem->variant->is($small))->toBeTrue()
        ->and($mediumItem->variant->is($medium))->toBeTrue();
});

it('merges repeated additions of the same variant', function () {
    [, $small] = cartVariantCatalog();
    $cart = Cart::create(['currency' => Currency::USD]);

    $cart->add($small, 2);
    $item = $cart->add($small, 3);

    expect($cart->items()->count())->toBe(1)
        ->and($item->quantity)->toBe(5);
});

it('sets and removes cart lines by variant identity', function () {
    [, $small, $medium] = cartVariantCatalog();
    $cart = Cart::create(['currency' => Currency::USD]);
    $cart->add($small);
    $cart->add($medium);

    $item = $cart->set($small, 4);
    $cart->remove($medium);

    expect($item->quantity)->toBe(4)
        ->and($cart->items)->toHaveCount(1)
        ->and($cart->items->first()->variant->is($small))->toBeTrue();
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
    $cart = Cart::create(['currency' => Currency::USD]);

    expect(fn () => $cart->add($variant, 1))
        ->toThrow(InvalidArgumentException::class, 'Cart item quantity is below the variant minimum quantity.');

    $item = $cart->add($variant, 2);

    expect($item->unitPrice()->amount())->toBe('1750')
        ->and($item->total()->amount())->toBe('3500')
        ->and($item->sku())->toBe('VARIANT-SKU')
        ->and($item->barcode())->toBe('0098765432106')
        ->and($item->availableStock())->toBe(3)
        ->and($item->allowsBackorders())->toBeFalse();

    expect(fn () => $cart->add($variant, 2))
        ->toThrow(InvalidArgumentException::class, 'Cart item quantity exceeds the variant maximum quantity.');
});

it('inherits nullable variant commerce values from the product', function () {
    [$product, $variant] = cartVariantCatalog();
    $cart = Cart::create(['currency' => Currency::USD]);
    $item = $cart->add($variant);

    expect($item->unitPrice()->amount())->toBe($product->price->amount())
        ->and($item->sku())->toBe($product->sku)
        ->and($item->barcode())->toBe($product->barcode)
        ->and($item->availableStock())->toBe($product->stock)
        ->and($item->allowsBackorders())->toBe($product->allow_backorders);
});

function cartVariantProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(3, true),
        'sku' => fake()->unique()->bothify('PRODUCT-####'),
        'barcode' => fake()->unique()->numerify('#############'),
        'price' => Price::of(1000),
        'stock' => 10,
        'allow_backorders' => true,
    ], $attributes));
}

/** @return array{Product, ProductVariant, ProductVariant} */
function cartVariantCatalog(array $firstVariantAttributes = []): array
{
    $product = cartVariantProduct();
    $size = ProductAttribute::create([
        'slug' => fake()->unique()->bothify('size-####'),
        'name' => 'Size',
    ]);
    $small = cartVariantValue($size, 'small');
    $medium = cartVariantValue($size, 'medium');
    $product->attributeValues()->attach([$small->id, $medium->id]);
    $variants = $product->generateVariants([$size]);

    $variants->first()->update($firstVariantAttributes);

    return [$product, $variants->first()->refresh(), $variants->last()->refresh()];
}

function cartVariantValue(ProductAttribute $attribute, string $slug): ProductAttributeValue
{
    return $attribute->values()->create([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'value' => $slug,
    ]);
}
