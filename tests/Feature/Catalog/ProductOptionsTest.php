<?php

use Larasell\Larasell\Catalog\Actions\SyncProductOptions;
use Larasell\Larasell\Catalog\Exceptions\InvalidVariantOptions;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductVariant;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinitionValue;
use Larasell\Larasell\Catalog\ProductId;
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

/** @param  array<string, list<string>>  $options  By definition slug, value slugs */
function syncSelection(array $options): array
{
    return collect($options)->mapWithKeys(fn (array $values, string $slug): array => [
        ($definition = OptionDefinition::query()->where('slug', $slug)->sole())->id => array_map(fn (string $value): int => optionValue($definition, $value)->id, $values),
    ])->all();
}

/** @return list<string> Each variant's values, e.g. "black/s" */
function combinations(Product $product): array
{
    return $product->variants()->with('optionValues.definitionValue')->get()
        ->map(fn (ProductVariant $variant): string => $variant->optionValues->sortBy('product_option_id')->map(fn ($value) => $value->definitionValue->slug)->implode('/'))
        ->sort()->values()->all();
}

/** A shirt in black and white, sizes s and m, with all four variants. */
function stockedShirt(): Product
{
    $color = optionDefinition('color', ['black', 'white', 'red']);
    $size = optionDefinition('size', ['s', 'm', 'l']);
    $product = shirt();
    $product->addOption($color, [optionValue($color, 'black'), optionValue($color, 'white')]);
    $product->addOption($size, [optionValue($size, 's'), optionValue($size, 'm')]);
    $product->createCombinations([
        [optionValue($color, 'black'), optionValue($size, 's')],
        [optionValue($color, 'black'), optionValue($size, 'm')],
        [optionValue($color, 'white'), optionValue($size, 's')],
        [optionValue($color, 'white'), optionValue($size, 'm')],
    ]);

    return $product;
}

it('adds options and values without creating variants', function () {
    // Arrange
    $product = stockedShirt();

    // Act
    app(SyncProductOptions::class)->handle(ProductId::of($product), syncSelection(['color' => ['black', 'white', 'red'], 'size' => ['s', 'm']]));

    // Assert
    expect($product->options()->first()->values()->count())->toBe(3)
        ->and(combinations($product))->toBe(['black/m', 'black/s', 'white/m', 'white/s']);
});

it('gives existing variants the first value of a new option', function () {
    // Arrange
    $product = stockedShirt();
    optionDefinition('fit', ['slim', 'loose']);

    // Act
    app(SyncProductOptions::class)->handle(ProductId::of($product), syncSelection(['color' => ['black', 'white'], 'size' => ['s', 'm'], 'fit' => ['loose', 'slim']]));

    // Assert
    expect(combinations($product))->toBe(['black/m/loose', 'black/s/loose', 'white/m/loose', 'white/s/loose']);
});

it('deletes the variants of a removed value, and only those', function () {
    // Arrange
    $product = stockedShirt();

    // Act
    app(SyncProductOptions::class)->handle(ProductId::of($product), syncSelection(['color' => ['black'], 'size' => ['s', 'm']]));

    // Assert
    expect(combinations($product))->toBe(['black/m', 'black/s'])
        ->and($product->variants()->where('is_default', true)->count())->toBe(1);
});

it('removes an option by keeping the variants of its first value', function () {
    // Arrange
    $product = stockedShirt();

    // Act
    app(SyncProductOptions::class)->handle(ProductId::of($product), syncSelection(['color' => ['black', 'white']]));

    // Assert
    expect(combinations($product))->toBe(['black', 'white'])
        ->and($product->options()->count())->toBe(1);
});

it('keeps one default variant without values when every option is removed', function () {
    // Arrange
    $product = stockedShirt();

    // Act
    app(SyncProductOptions::class)->handle(ProductId::of($product), []);

    // Assert
    expect($product->variants()->count())->toBe(1)
        ->and($product->variants()->sole()->is_default)->toBeTrue()
        ->and($product->options()->exists())->toBeFalse();
});

it('tells how many variants a change deletes before making it', function () {
    // Arrange
    $product = stockedShirt();

    // Act
    $count = app(SyncProductOptions::class)->deletions(ProductId::of($product), syncSelection(['color' => ['black'], 'size' => ['s', 'm']]));

    // Assert
    expect($count)->toBe(2)
        ->and($product->variants()->count())->toBe(4);
});

it('rejects values of another option and options without values, changing nothing', function (array $selection) {
    // Arrange
    $product = stockedShirt();

    // Act & Assert
    expect(fn () => app(SyncProductOptions::class)->handle(ProductId::of($product), syncSelection($selection)))
        ->toThrow(InvalidVariantOptions::class)
        ->and(combinations($product))->toBe(['black/m', 'black/s', 'white/m', 'white/s']);
})->with([
    'no values' => [['color' => ['black', 'white'], 'size' => []]],
]);

it('rejects a value of another definition', function () {
    // Arrange
    $product = stockedShirt();
    $color = OptionDefinition::query()->where('slug', 'color')->sole();

    // Act & Assert
    expect(fn () => app(SyncProductOptions::class)->handle(ProductId::of($product), [$color->id => [optionValue(OptionDefinition::query()->where('slug', 'size')->sole(), 's')->id]]))
        ->toThrow(InvalidVariantOptions::class);
});
