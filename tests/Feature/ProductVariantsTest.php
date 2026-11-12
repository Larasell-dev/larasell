<?php

use Illuminate\Database\QueryException;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductVariant;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;

function variantProduct(): Product
{
    return Product::create([
        'name' => fake()->words(3, true),
        'price' => Price::of(1000),
    ]);
}

it('does not allow backorders unless enabled', function () {
    // Arrange
    $product = variantProduct();

    // Act
    [$variant] = optionVariants($product, [optionDefinition('size', ['small', 'medium'])]);

    // Assert
    expect($product->fresh()->allow_backorders)->toBeFalse()
        ->and($variant->fresh()->allowsBackorders())->toBeFalse();
});

it('requires non-null variant identifiers to be unique', function (string $column) {
    // Arrange
    $size = optionDefinition('size', ['small', 'medium']);
    [$first] = optionVariants(variantProduct(), [$size]);
    $first->update([$column => 'DUPLICATE']);
    [$second] = optionVariants(variantProduct(), [$size]);

    // Act & Assert
    expect(fn () => $second->update([$column => 'DUPLICATE']))
        ->toThrow(QueryException::class);
})->with(['sku', 'barcode']);

it('hides every variant of a hidden product, whatever their own status', function () {
    // Arrange
    $product = variantProduct();
    [$visible, $hidden] = optionVariants($product, [optionDefinition('size', ['small', 'medium'])]);
    $hidden->update(['status' => Visibility::Hidden]);

    // Act
    $product->update(['status' => Visibility::Hidden]);

    // Assert
    expect($visible->fresh()->isVisible())->toBeFalse()
        ->and(ProductVariant::query()->visible()->whereBelongsTo($product)->exists())->toBeFalse();

    // Act
    $product->update(['status' => Visibility::Visible]);

    // Assert
    expect($visible->fresh()->isVisible())->toBeTrue()
        ->and(ProductVariant::query()->visible()->whereBelongsTo($product)->pluck('id')->all())->toBe([$visible->id]);
});

it('exposes visible variants with the parent product already set', function () {
    // Arrange
    $product = variantProduct();
    [$visible, $hidden] = optionVariants($product, [
        optionDefinition('size', ['small']),
        optionDefinition('color', ['black', 'white']),
    ]);
    $hidden->update(['status' => Visibility::Hidden]);

    // Act
    $product->load('visibleVariants');

    // Assert
    expect($product->visibleVariants)->toHaveCount(1)
        ->and($product->visibleVariants->sole()->is($visible))->toBeTrue()
        ->and($product->visibleVariants->sole()->relationLoaded('product'))->toBeTrue()
        ->and($product->visibleVariants->sole()->product)->toBe($product)
        ->and($product->visibleVariants->sole()->name())->toBe('Small / Black');
});
