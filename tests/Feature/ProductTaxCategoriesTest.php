<?php

use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

it('defaults products to the standard tax category', function () {
    $product = Product::create([
        'slug' => 'standard-product',
        'name' => 'Standard product',
        'price' => Price::of(1000),
    ]);

    expect($product->tax_category)->toBe('standard')
        ->and($product->defaultVariant()->effectiveTaxCategory())->toBe('standard');
});

it('allows a variant to override its product tax category', function () {
    $product = Product::create([
        'slug' => 'book',
        'name' => 'Book',
        'price' => Price::of(1000),
        'tax_category' => 'reduced',
    ]);
    $variant = $product->defaultVariant();

    expect($variant->effectiveTaxCategory())->toBe('reduced');

    $variant->update(['tax_category' => 'standard']);

    expect($variant->effectiveTaxCategory())->toBe('standard');
});
