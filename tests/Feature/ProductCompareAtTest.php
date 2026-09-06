<?php

use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Price;

it('defaults products to no compare-at price', function () {
    $product = Product::create([
        'slug' => 'plain-tee',
        'name' => 'Plain tee',
        'price' => Price::of(1000),
    ]);

    expect($product->compare_at)->toBeNull()
        ->and($product->onSale())->toBeFalse()
        ->and($product->defaultVariant()->compareAtPrice())->toBeNull()
        ->and($product->defaultVariant()->onSale())->toBeFalse();
});

it('treats a product as on sale when compare-at is higher than the selling price', function () {
    $product = Product::create([
        'slug' => 'sale-tee',
        'name' => 'Sale tee',
        'price' => Price::of(1000),
        'compare_at' => Price::of(1500),
    ]);

    expect($product->compare_at)->toEqual(Price::of(1500))
        ->and($product->onSale())->toBeTrue()
        ->and($product->defaultVariant()->compareAtPrice())->toEqual(Price::of(1500))
        ->and($product->defaultVariant()->onSale())->toBeTrue();
});

it('does not treat a product as on sale when compare-at is missing, equal, or lower', function (mixed $compareAt) {
    $product = Product::create([
        'slug' => fake()->unique()->slug(),
        'name' => 'Reference tee',
        'price' => Price::of(1000),
        'compare_at' => $compareAt,
    ]);

    expect($product->onSale())->toBeFalse()
        ->and($product->defaultVariant()->onSale())->toBeFalse();
})->with([
    'missing' => [null],
    'equal' => [Price::of(1000)],
    'lower' => [Price::of(800)],
]);

it('keeps a leftover compare-at when a sale ends by raising the selling price first', function () {
    $product = Product::create([
        'slug' => 'ended-sale-tee',
        'name' => 'Ended sale tee',
        'price' => Price::of(1000),
        'compare_at' => Price::of(1500),
    ]);

    $product->update(['price' => Price::of(1500)]);

    expect($product->fresh())
        ->price->toEqual(Price::of(1500))
        ->compare_at->toEqual(Price::of(1500))
        ->onSale()->toBeFalse();
});

it('allows setting compare-at before lowering the selling price', function () {
    $product = Product::create([
        'slug' => 'upcoming-sale-tee',
        'name' => 'Upcoming sale tee',
        'price' => Price::of(1500),
    ]);

    $product->update(['compare_at' => Price::of(1500)]);

    expect($product->fresh()->onSale())->toBeFalse();

    $product->update(['price' => Price::of(1000)]);

    expect($product->fresh())
        ->price->toEqual(Price::of(1000))
        ->compare_at->toEqual(Price::of(1500))
        ->onSale()->toBeTrue();
});

it('accepts selling price and compare-at updates in either order', function (array $first, array $second) {
    $product = Product::create([
        'slug' => fake()->unique()->slug(),
        'name' => 'Either order tee',
        'price' => Price::of(1500),
    ]);

    $product->update($first);
    $product->update($second);

    expect($product->fresh())
        ->price->toEqual(Price::of(1000))
        ->compare_at->toEqual(Price::of(1500))
        ->onSale()->toBeTrue();
})->with([
    'compare-at then price' => [
        ['compare_at' => Price::of(1500)],
        ['price' => Price::of(1000)],
    ],
    'price then compare-at' => [
        ['price' => Price::of(1000)],
        ['compare_at' => Price::of(1500)],
    ],
]);

it('syncs the default variant compare-at when the product compare-at changes', function () {
    $product = Product::create([
        'slug' => 'synced-tee',
        'name' => 'Synced tee',
        'price' => Price::of(1000),
        'compare_at' => Price::of(1500),
    ]);

    $product->update(['compare_at' => Price::of(2000)]);

    expect($product->defaultVariant()->fresh()->compare_at)->toEqual(Price::of(2000));

    $product->update(['compare_at' => null]);

    expect($product->defaultVariant()->fresh()->compare_at)->toBeNull()
        ->and($product->fresh()->onSale())->toBeFalse();
});

it('lets a variant inherit or override the product compare-at price', function () {
    $product = Product::create([
        'slug' => 'variant-sale-tee',
        'name' => 'Variant sale tee',
        'price' => Price::of(1000),
        'compare_at' => Price::of(1800),
    ]);
    $size = ProductAttribute::create(['slug' => 'compare-size', 'name' => 'Size']);
    $small = $size->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'small']);
    $medium = $size->values()->create(['slug' => 'medium', 'name' => 'Medium', 'value' => 'medium']);
    $product->attributeValues()->attach([$small->id, $medium->id]);
    $product->variantDimensions()->sync([$size->id => ['position' => 0]]);
    $inherited = $product->createVariant([$small]);
    $overridden = $product->createVariant([$medium], [
        'price' => Price::of(900),
        'compare_at' => Price::of(1400),
    ]);

    expect($inherited->compare_at)->toBeNull()
        ->and($inherited->compareAtPrice())->toEqual(Price::of(1800))
        ->and($inherited->onSale())->toBeTrue()
        ->and($overridden->compareAtPrice())->toEqual(Price::of(1400))
        ->and($overridden->onSale())->toBeTrue();
});

it('charges the selling price when a compare-at price is set', function () {
    $cart = Cart::create(['currency' => Currency::USD]);
    $product = Product::create([
        'slug' => 'cart-sale-tee',
        'name' => 'Cart sale tee',
        'price' => Price::of(1000),
        'compare_at' => Price::of(2000),
    ]);

    $item = $cart->add($product, 2);

    expect($item->unitPrice())->toEqual(Price::of(1000))
        ->and($item->total())->toEqual(Price::of(2000))
        ->and($cart->subtotal())->toEqual(Price::of(2000));
});
