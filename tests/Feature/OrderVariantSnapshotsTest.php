<?php

use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Models\ProductVariant;
use Larasell\Larasell\Price;

it('snapshots the selected variant and its ordered attribute labels', function () {
    [$cart, $variant, $size, $small, $color, $black] = orderSnapshotVariantCart();
    $cart->add($variant);

    $item = orderSnapshotCheckout($cart)->items->sole();

    expect($item->variant_name)->toBe('Small / Black')
        ->and($item->variant_options)->toEqual([
            'size' => [
                'attribute_id' => $size->id,
                'attribute_slug' => 'size',
                'attribute_name' => 'Size',
                'value_id' => $small->id,
                'value_slug' => 'small',
                'value_name' => 'Small',
            ],
            'color' => [
                'attribute_id' => $color->id,
                'attribute_slug' => 'color',
                'attribute_name' => 'Color',
                'value_id' => $black->id,
                'value_slug' => 'black',
                'value_name' => 'Black',
            ],
        ]);
});

it('keeps variant snapshots unchanged when catalog data changes', function () {
    [$cart, $variant, $size, $small] = orderSnapshotVariantCart();
    $cart->add($variant);
    $item = orderSnapshotCheckout($cart)->items->sole();
    $snapshot = $item->only([
        'product_name',
        'product_slug',
        'product_sku',
        'product_barcode',
        'variant_name',
        'variant_options',
    ]);

    $variant->product->update(['name' => 'Renamed shirt', 'slug' => 'renamed-shirt']);
    $variant->update(['sku' => 'NEW-SKU', 'barcode' => '9999999999999']);
    $size->update(['name' => 'Renamed size', 'slug' => 'renamed-size']);
    $small->update(['name' => 'Renamed small', 'slug' => 'renamed-small']);

    expect($item->fresh()->only(array_keys($snapshot)))->toBe($snapshot);
});

it('keeps historical variant snapshots after the variant is deleted', function () {
    [$cart, $variant] = orderSnapshotVariantCart();
    $cart->add($variant);
    $item = orderSnapshotCheckout($cart)->items->sole();

    $variant->delete();
    $item->refresh();

    expect($item->product_variant_id)->toBeNull()
        ->and($item->variant)->toBeNull()
        ->and($item->variant_name)->toBe('Small / Black')
        ->and($item->variant_options)->toHaveKeys(['size', 'color']);
});

it('snapshots simple products without variant options', function () {
    $product = Product::create([
        'slug' => 'simple-snapshot-product',
        'name' => ['en' => 'Simple product', 'de' => 'Einfaches Produkt'],
        'price' => Price::of(1000),
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::create(['currency' => Currency::EUR]);
    $cart->add($product);

    $item = orderSnapshotCheckout($cart)->items->sole();

    expect($item->variant_name)->toBe('Simple product')
        ->and($item->variant_options)->toBe([]);
});

/** @return array{Cart, ProductVariant, ProductAttribute, mixed, ProductAttribute, mixed} */
function orderSnapshotVariantCart(): array
{
    $product = Product::create([
        'slug' => fake()->unique()->slug(),
        'name' => 'Classic T-shirt',
        'price' => Price::of(1000),
        'stock' => 10,
        'status' => Visibility::Visible,
    ]);
    $size = ProductAttribute::create(['slug' => 'size', 'name' => 'Size']);
    $small = $size->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'small']);
    $medium = $size->values()->create(['slug' => 'medium', 'name' => 'Medium', 'value' => 'medium']);
    $color = ProductAttribute::create(['slug' => 'color', 'name' => 'Color']);
    $black = $color->values()->create(['slug' => 'black', 'name' => 'Black', 'value' => 'black']);
    $product->attributeValues()->attach([$small->id, $medium->id, $black->id]);
    $variants = $product->generateVariants([$size, $color]);
    $variants->each->update(['status' => Visibility::Visible]);
    $variant = $product->variantFor([
        'size' => 'small',
        'color' => 'black',
    ]);
    $variant->update([
        'sku' => 'TSHIRT-BLK-S',
        'barcode' => '4012345678901',
    ]);

    return [Cart::create(['currency' => Currency::EUR]), $variant->refresh(), $size, $small, $color, $black];
}

function orderSnapshotCheckout(Cart $cart): mixed
{
    return app(Checkout::class)->create($cart, [
        'customer_email' => 'snapshot@example.com',
        'customer_name' => 'Snapshot Customer',
    ])->order;
}
