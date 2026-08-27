<?php

use InvalidArgumentException;
use Larasell\Larasell\Enums\ProductOptionType;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductOption;
use Larasell\Larasell\Models\ProductOptionValue;
use Larasell\Larasell\Price;

it('defines option values for a product option', function () {
    $option = ProductOption::create([
        'slug' => 'size',
        'name' => 'Size',
    ]);

    $small = $option->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'small',
        'position' => 0,
    ]);

    $large = $option->values()->create([
        'slug' => 'large',
        'name' => 'Large',
        'value' => 'large',
        'position' => 1,
    ]);

    expect($option->values()->pluck('name')->all())->toBe(['Small', 'Large'])
        ->and($small->product_option_id)->toBe($option->id)
        ->and($large->position)->toBe(1);
});

it('assigns option values to a product', function () {
    $product = productWithOptions();

    $size = ProductOption::create([
        'slug' => 'size',
        'name' => 'Size',
    ]);

    $small = $size->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'small',
    ]);

    $color = ProductOption::create([
        'slug' => 'color',
        'name' => 'Color',
    ]);

    $black = $color->values()->create([
        'slug' => 'black',
        'name' => 'Black',
        'value' => 'black',
    ]);

    $product->optionValues()->attach([$small->id, $black->id]);

    $product = $product->fresh('optionValues.option');

    expect($product->optionValues)->toHaveCount(2)
        ->and($product->optionValues->pluck('name')->all())->toBe(['Small', 'Black'])
        ->and($product->optionValues->first()->option)->toBeInstanceOf(ProductOption::class)
        ->and($product->optionValues->first()->option->name)->toBe('Size');
});

it('can eager load option values and their options with a scope', function () {
    $product = productWithOptions();

    $size = ProductOption::create([
        'slug' => 'size',
        'name' => 'Size',
    ]);

    $small = $size->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'small',
    ]);

    $product->optionValues()->attach($small);

    $product = Product::query()
        ->withOptions()
        ->whereKey($product)
        ->firstOrFail();

    expect($product->relationLoaded('optionValues'))->toBeTrue()
        ->and($product->optionValues->first()->relationLoaded('option'))->toBeTrue()
        ->and($product->optionValues->first()->option->name)->toBe('Size');
});

it('can query products from an option value', function () {
    $product = productWithOptions();

    $option = ProductOption::create([
        'slug' => 'size',
        'name' => 'Size',
    ]);

    $value = ProductOptionValue::create([
        'product_option_id' => $option->id,
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'small',
    ]);

    $value->products()->attach($product);

    expect($value->products()->first()->is($product))->toBeTrue();
});

it('stores typed option values', function (ProductOptionType $type, mixed $rawValue) {
    $option = ProductOption::create([
        'slug' => $type->value,
        'name' => ucfirst($type->value),
        'type' => $type,
    ]);

    $value = $option->values()->create([
        'slug' => 'value',
        'name' => 'Value',
        'value' => $rawValue,
    ])->fresh();

    expect($value->value)->toBe($rawValue);
})->with([
    'text' => [ProductOptionType::Text, 'cotton'],
    'number integer' => [ProductOptionType::Number, 42],
    'number float' => [ProductOptionType::Number, 12.5],
    'boolean true' => [ProductOptionType::Boolean, true],
    'boolean false' => [ProductOptionType::Boolean, false],
]);

it('rejects option values that do not match the option type', function (ProductOptionType $type, mixed $rawValue, string $message) {
    $option = ProductOption::create([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(2, true),
        'type' => $type,
    ]);

    expect(fn () => $option->values()->create([
        'slug' => 'invalid',
        'name' => 'Invalid',
        'value' => $rawValue,
    ]))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'boolean value for text option' => [ProductOptionType::Text, true, 'Product option value must be text.'],
    'number value for text option' => [ProductOptionType::Text, 10, 'Product option value must be text.'],
    'text value for boolean option' => [ProductOptionType::Boolean, 'true', 'Product option value must be boolean.'],
    'number value for boolean option' => [ProductOptionType::Boolean, 1, 'Product option value must be boolean.'],
    'text value for number option' => [ProductOptionType::Number, '10', 'Product option value must be number.'],
    'boolean value for number option' => [ProductOptionType::Number, false, 'Product option value must be number.'],
]);

function productWithOptions(array $attributes = []): Product
{
    return Product::create(array_merge([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(3, true),
        'price' => Price::of(1000),
    ], $attributes));
}
