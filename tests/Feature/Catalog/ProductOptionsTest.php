<?php

use Larasell\Larasell\Catalog\Exceptions\InvalidVariantOptions;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinitionValue;
use Larasell\Larasell\Price;

function optionDefinition(string $slug, array $values): OptionDefinition
{
    // Arrange
    $definition = OptionDefinition::create(['slug' => $slug, 'name' => ['en' => ucfirst($slug)]]);

    foreach ($values as $position => $value) {
        $definition->values()->create(['slug' => $value, 'name' => ['en' => ucfirst($value)], 'position' => $position]);
    }

    return $definition;
}

function optionValue(OptionDefinition $definition, string $slug): OptionDefinitionValue
{
    return $definition->values()->where('slug', $slug)->sole();
}

function shirt(): Product
{
    return Product::create(['name' => 'Shirt', 'price' => Price::of(1000)]);
}

it('turns the default variant into the first combination instead of deleting it', function () {
    // Arrange
    $size = optionDefinition('size', ['s', 'm']);
    $product = shirt();
    $defaultId = $product->defaultVariant()->id;
    $product->addOption($size, [optionValue($size, 's'), optionValue($size, 'm')]);

    // Act
    $variants = $product->createCombinations([[optionValue($size, 's')], [optionValue($size, 'm')]]);

    // Assert
    expect($variants)->toHaveCount(2)
        ->and($variants[0]->id)->toBe($defaultId)
        ->and($variants[0]->name('en'))->toBe('S')
        ->and($product->variants()->count())->toBe(2)
        ->and($product->variants()->where('is_default', true)->count())->toBe(1);
});

it('creates only the selected combinations, not every one', function () {
    // Arrange
    $color = optionDefinition('color', ['black', 'white']);
    $size = optionDefinition('size', ['s', 'm']);
    $product = shirt();
    $product->addOption($color, [optionValue($color, 'black'), optionValue($color, 'white')]);
    $product->addOption($size, [optionValue($size, 's'), optionValue($size, 'm')]);

    // Act
    $product->createCombinations([
        [optionValue($color, 'black'), optionValue($size, 's')],
        [optionValue($color, 'white'), optionValue($size, 'm')],
    ]);

    // Assert
    expect($product->variants()->get()->map->name('en')->sort()->values()->all())
        ->toBe(['Black / S', 'White / M']);
});

it('keeps existing variants when an option is added later, giving them its first value', function () {
    // Arrange
    $size = optionDefinition('size', ['s', 'm']);
    $color = optionDefinition('color', ['black', 'white']);
    $product = shirt();
    $product->addOption($size, [optionValue($size, 's'), optionValue($size, 'm')]);
    [$small] = $product->createCombinations([[optionValue($size, 's')]]);

    // Act
    $product->addOption($color, [optionValue($color, 'white'), optionValue($color, 'black')]);

    // Assert
    expect($small->fresh()->name('en'))->toBe('S / White')
        ->and($product->variants()->count())->toBe(1);
});

it('adds a value to an option without touching existing variants', function () {
    // Arrange
    $size = optionDefinition('size', ['s', 'm', 'l']);
    $product = shirt();
    $product->addOption($size, [optionValue($size, 's')]);
    [$small] = $product->createCombinations([[optionValue($size, 's')]]);

    // Act
    $product->addOptionValue($size, optionValue($size, 'l'));
    $product->createCombinations([[optionValue($size, 'l')]]);

    // Assert
    expect($small->fresh()->exists)->toBeTrue()
        ->and($product->variants()->count())->toBe(2);
});

it('rejects incomplete, duplicate and foreign combinations', function () {
    // Arrange
    $size = optionDefinition('size', ['s', 'm']);
    $color = optionDefinition('color', ['black']);
    $product = shirt();
    $product->addOption($size, [optionValue($size, 's')]);
    $product->addOption($color, [optionValue($color, 'black')]);
    $product->createCombinations([[optionValue($size, 's'), optionValue($color, 'black')]]);

    // Act & Assert
    expect(fn () => $product->createCombinations([[optionValue($size, 's')]]))->toThrow(InvalidVariantOptions::class)
        ->and(fn () => $product->createCombinations([[optionValue($color, 'black'), optionValue($size, 's')]]))->toThrow(InvalidVariantOptions::class)
        ->and(fn () => $product->createCombinations([[optionValue($size, 'm'), optionValue($color, 'black')]]))->toThrow(InvalidVariantOptions::class);
});

it('lets a product without options keep exactly its default variant', function () {
    // Arrange
    $product = shirt();

    // Act & Assert
    expect($product->defaultVariant()->options())->toBe([])
        ->and($product->defaultVariant()->name('en'))->toBe('Shirt');
});
