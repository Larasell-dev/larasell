<?php

use InvalidArgumentException;
use Larasell\Larasell\Enums\ProductAttributeType;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Models\ProductAttributeValue;
use Larasell\Larasell\Price;

it('defines values for a product attribute', function () {
    $attribute = ProductAttribute::create([
        'slug' => 'size',
        'name' => 'Size',
    ]);

    $small = $attribute->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'small',
        'position' => 0,
    ]);

    $large = $attribute->values()->create([
        'slug' => 'large',
        'name' => 'Large',
        'value' => 'large',
        'position' => 1,
    ]);

    expect($attribute->values()->orderBy('position')->pluck('name')->all())->toBe(['Small', 'Large'])
        ->and($small->product_attribute_id)->toBe($attribute->id)
        ->and($large->position)->toBe(1);
});

it('assigns attribute values to a product', function () {
    $product = productWithAttributes();

    $size = ProductAttribute::create([
        'slug' => 'size',
        'name' => 'Size',
    ]);

    $small = $size->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'small',
    ]);

    $color = ProductAttribute::create([
        'slug' => 'color',
        'name' => 'Color',
    ]);

    $black = $color->values()->create([
        'slug' => 'black',
        'name' => 'Black',
        'value' => 'black',
    ]);

    $product->attributeValues()->attach([$small->id, $black->id]);

    $product = $product->fresh('attributeValues.attribute');

    expect($product->attributeValues)->toHaveCount(2)
        ->and($product->attributeValues->pluck('name')->all())->toBe(['Small', 'Black'])
        ->and($product->attributeValues->first()->attribute)->toBeInstanceOf(ProductAttribute::class)
        ->and($product->attributeValues->first()->attribute->name)->toBe('Size');
});

it('can eager load attribute values and their attributes with a scope', function () {
    $product = productWithAttributes();

    $size = ProductAttribute::create([
        'slug' => 'size',
        'name' => 'Size',
    ]);

    $small = $size->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'small',
    ]);

    $product->attributeValues()->attach($small);

    $product = Product::query()
        ->withAttributeValues()
        ->whereKey($product)
        ->firstOrFail();

    expect($product->relationLoaded('attributeValues'))->toBeTrue()
        ->and($product->attributeValues->first()->relationLoaded('attribute'))->toBeTrue()
        ->and($product->attributeValues->first()->attribute->name)->toBe('Size');
});

it('can query products from an attribute value', function () {
    $product = productWithAttributes();

    $attribute = ProductAttribute::create([
        'slug' => 'size',
        'name' => 'Size',
    ]);

    $value = ProductAttributeValue::create([
        'product_attribute_id' => $attribute->id,
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'small',
    ]);

    $value->products()->attach($product);

    expect($value->products()->first()->is($product))->toBeTrue();
});

it('stores typed attribute values', function (ProductAttributeType $type, mixed $rawValue) {
    $attribute = ProductAttribute::create([
        'slug' => $type->value,
        'name' => ucfirst($type->value),
        'type' => $type,
    ]);

    $value = $attribute->values()->create([
        'slug' => 'value',
        'name' => 'Value',
        'value' => $rawValue,
    ])->fresh();

    expect($value->value)->toBe($rawValue);
})->with([
    'text' => [ProductAttributeType::Text, 'cotton'],
    'number integer' => [ProductAttributeType::Number, 42],
    'number float' => [ProductAttributeType::Number, 12.5],
    'boolean true' => [ProductAttributeType::Boolean, true],
    'boolean false' => [ProductAttributeType::Boolean, false],
]);

it('rejects attribute values that do not match the attribute type', function (ProductAttributeType $type, mixed $rawValue, string $message) {
    $attribute = ProductAttribute::create([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(2, true),
        'type' => $type,
    ]);

    expect(fn () => $attribute->values()->create([
        'slug' => 'invalid',
        'name' => 'Invalid',
        'value' => $rawValue,
    ]))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'boolean value for text attribute' => [ProductAttributeType::Text, true, 'Product attribute value must be text.'],
    'number value for text attribute' => [ProductAttributeType::Text, 10, 'Product attribute value must be text.'],
    'text value for boolean attribute' => [ProductAttributeType::Boolean, 'true', 'Product attribute value must be boolean.'],
    'number value for boolean attribute' => [ProductAttributeType::Boolean, 1, 'Product attribute value must be boolean.'],
    'text value for number attribute' => [ProductAttributeType::Number, '10', 'Product attribute value must be number.'],
    'boolean value for number attribute' => [ProductAttributeType::Number, false, 'Product attribute value must be number.'],
]);

function productWithAttributes(array $attributes = []): Product
{
    return Product::create(array_merge([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(3, true),
        'price' => Price::of(1000),
    ], $attributes));
}
