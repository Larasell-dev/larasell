<?php

use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductVariant;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
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

it('snapshots variant names in the checkout locale', function () {
    [$cart, $variant, $size, $small, $color, $black] = orderSnapshotVariantCart();
    $size->update(['name' => ['en' => 'Size', 'de' => 'Größe']]);
    $small->update(['name' => ['en' => 'Small', 'de' => 'Klein']]);
    $black->update(['name' => ['en' => 'Black', 'de' => 'Schwarz']]);
    $cart->add($variant);

    App::setLocale('de');
    $item = orderSnapshotCheckout($cart)->items->sole();

    expect($item->variant_name)->toBe('Klein / Schwarz')
        ->and($item->variant_options['size']['attribute_name'])->toBe('Größe')
        ->and($item->variant_options['size']['value_name'])->toBe('Klein')
        ->and($item->variant_options['color']['attribute_name'])->toBe('Color');
});

it('keeps variant snapshots unchanged when catalog data changes', function () {
    [$cart, $variant, $size, $small] = orderSnapshotVariantCart();
    $cart->add($variant);
    $item = orderSnapshotCheckout($cart)->items->sole();
    $productNameSnapshot = $item->product_name->all();
    $snapshot = $item->only([
        'product_sku',
        'product_barcode',
        'variant_name',
        'variant_options',
    ]);

    $variant->product->update(['name' => 'Renamed shirt']);
    $variant->update(['sku' => 'NEW-SKU', 'barcode' => '9999999999999']);
    $size->update(['name' => 'Renamed size', 'slug' => 'renamed-size']);
    $small->update(['name' => 'Renamed small', 'slug' => 'renamed-small']);

    $item->refresh();

    expect($item->product_name->all())->toBe($productNameSnapshot)
        ->and($item->only(array_keys($snapshot)))->toBe($snapshot);
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
        'name' => ['en' => 'Simple product', 'de' => 'Einfaches Produkt'],
        'price' => Price::of(1000),
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::EUR]);
    $cart->add($product);

    $item = orderSnapshotCheckout($cart)->items->sole();

    expect($item->product_name->all())->toEqual([
        'en' => 'Simple product',
        'de' => 'Einfaches Produkt',
    ])->and($item->variant_name)->toBe('Simple product')
        ->and($item->variant_options)->toBe([]);
});

/** @return array{Cart, ProductVariant, OptionDefinition, mixed, OptionDefinition, mixed} */
function orderSnapshotVariantCart(): array
{
    $product = Product::create([
        'name' => 'Classic T-shirt',
        'price' => Price::of(1000),
        'stock' => 10,
        'status' => Visibility::Visible,
    ]);
    $size = optionDefinition('size', ['small', 'medium']);
    $color = optionDefinition('color', ['black']);
    optionVariants($product, [$size, $color]);
    $small = optionValue($size, 'small');
    $black = optionValue($color, 'black');
    $variant = optionVariant($product, [
        'size' => 'small',
        'color' => 'black',
    ]);
    $variant->update([
        'sku' => 'TSHIRT-BLK-S',
        'barcode' => '4012345678901',
    ]);

    return [Cart::createIn(shopContext(), ['currency' => Currency::EUR]), $variant->refresh(), $size, $small, $color, $black];
}

function orderSnapshotCheckout(Cart $cart): mixed
{
    return app(Checkout::class)->create($cart, [
        'customer_email' => 'snapshot@example.com',
        'customer_name' => 'Snapshot Customer',
    ])->order;
}
