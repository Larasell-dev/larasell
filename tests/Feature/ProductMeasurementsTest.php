<?php

use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Dimensions;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\LengthUnit;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Enums\WeightUnit;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Price;
use Larasell\Larasell\Weight;

it('defaults products to no weight or dimensions', function () {
    $product = Product::create([
        'name' => 'Plain tee',
        'price' => Price::of(1000),
    ]);

    expect($product->weight)->toBeNull()
        ->and($product->dimensions)->toBeNull()
        ->and($product->defaultVariant()->effectiveWeight())->toBeNull()
        ->and($product->defaultVariant()->effectiveDimensions())->toBeNull();
});

it('persists product weight and dimensions which the default variant inherits', function () {
    $product = Product::create([
        'name' => 'Parcel tee',
        'price' => Price::of(1000),
        'weight' => Weight::of(450, WeightUnit::Gram),
        'dimensions' => Dimensions::of(30, 20, 2, LengthUnit::Centimeter),
    ]);

    expect($product->weight?->equals(Weight::of('0.45', WeightUnit::Kilogram)))->toBeTrue()
        ->and($product->dimensions?->equals(Dimensions::of(300, 200, 20, LengthUnit::Millimeter)))->toBeTrue()
        ->and($product->defaultVariant()->effectiveWeight()?->equals(Weight::of(450, WeightUnit::Gram)))->toBeTrue()
        ->and($product->defaultVariant()->effectiveDimensions()?->equals(Dimensions::of(30, 20, 2, LengthUnit::Centimeter)))->toBeTrue();
});

it('lets the default variant follow the product measurements change', function () {
    $product = Product::create([
        'name' => 'Synced parcel',
        'price' => Price::of(1000),
        'weight' => Weight::of(400, WeightUnit::Gram),
    ]);

    $product->update([
        'weight' => Weight::of(500, WeightUnit::Gram),
        'dimensions' => Dimensions::of(10, 10, 10, LengthUnit::Centimeter),
    ]);

    $variant = $product->defaultVariant()->fresh();

    expect($variant->effectiveWeight()?->equals(Weight::of(500, WeightUnit::Gram)))->toBeTrue()
        ->and($variant->effectiveDimensions()?->equals(Dimensions::of(10, 10, 10, LengthUnit::Centimeter)))->toBeTrue();

    $product->update(['weight' => null, 'dimensions' => null]);

    expect($product->defaultVariant()->fresh()->effectiveWeight())->toBeNull()
        ->and($product->defaultVariant()->fresh()->effectiveDimensions())->toBeNull();
});

it('lets a variant inherit or override the product measurements', function () {
    // Arrange
    $product = Product::create([
        'name' => 'Variant parcel',
        'price' => Price::of(1000),
        'weight' => Weight::of(400, WeightUnit::Gram),
        'dimensions' => Dimensions::of(30, 20, 2, LengthUnit::Centimeter),
    ]);
    [$inherited, $overridden] = optionVariants($product, [optionDefinition('size', ['small', 'large'])]);

    // Act
    $overridden->update([
        'weight_inheritance' => 'overridden',
        'dimensions_inheritance' => 'overridden',
        'weight' => Weight::of(600, WeightUnit::Gram),
        'dimensions' => Dimensions::of(40, 25, 3, LengthUnit::Centimeter),
    ]);

    // Assert
    expect($inherited->weight)->toBeNull()
        ->and($inherited->effectiveWeight()?->equals(Weight::of(400, WeightUnit::Gram)))->toBeTrue()
        ->and($inherited->effectiveDimensions()?->equals(Dimensions::of(30, 20, 2, LengthUnit::Centimeter)))->toBeTrue()
        ->and($overridden->effectiveWeight()?->equals(Weight::of(600, WeightUnit::Gram)))->toBeTrue()
        ->and($overridden->effectiveDimensions()?->equals(Dimensions::of(40, 25, 3, LengthUnit::Centimeter)))->toBeTrue();
});

it('sums cart weight from effective variant weights and quantities', function () {
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::EUR]);
    $light = Product::create([
        'name' => 'Light parcel',
        'price' => Price::of(1000),
        'weight' => Weight::of(500, WeightUnit::Gram),
        'dimensions' => Dimensions::of(20, 10, 5, LengthUnit::Centimeter),
        'status' => Visibility::Visible,
    ]);
    $heavy = Product::create([
        'name' => 'Heavy parcel',
        'price' => Price::of(1000),
        'weight' => Weight::of(1, WeightUnit::Kilogram),
        'status' => Visibility::Visible,
    ]);

    $lightItem = $cart->add($light, 2);
    $heavyItem = $cart->add($heavy);

    expect($lightItem->weight()?->equals(Weight::of(1, WeightUnit::Kilogram)))->toBeTrue()
        ->and($lightItem->dimensions()?->equals(Dimensions::of(20, 10, 5, LengthUnit::Centimeter)))->toBeTrue()
        ->and($heavyItem->weight()?->equals(Weight::of(1000, WeightUnit::Gram)))->toBeTrue()
        ->and($cart->weight()?->equals(Weight::of(2, WeightUnit::Kilogram)))->toBeTrue();
});

it('returns no cart weight when any line is missing a weight', function () {
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::EUR]);
    $weighed = Product::create([
        'name' => 'Weighed parcel',
        'price' => Price::of(1000),
        'weight' => Weight::of(500, WeightUnit::Gram),
    ]);
    $unknown = Product::create([
        'name' => 'Unknown parcel',
        'price' => Price::of(1000),
    ]);

    $cart->add($weighed);
    $cart->add($unknown);

    expect($cart->weight())->toBeNull();
});
