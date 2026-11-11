<?php

use Larasell\Larasell\Catalog\Actions\SetProductAttributeValues;
use Larasell\Larasell\Catalog\AttributeCardinality;
use Larasell\Larasell\Catalog\AttributeScope;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Exceptions\InvalidAttributeValues;
use Larasell\Larasell\Catalog\Exceptions\InvalidInheritance;
use Larasell\Larasell\Catalog\InheritanceState;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Price;

function scopedAttribute(string $slug, AttributeScope $scope, array $values, AttributeCardinality $cardinality = AttributeCardinality::Single): ProductAttribute
{
    $attribute = ProductAttribute::create(['slug' => $slug, 'name' => ['en' => ucfirst($slug)], 'scope' => $scope, 'cardinality' => $cardinality]);

    foreach ($values as $value) {
        $attribute->values()->create(['slug' => $value, 'name' => ['en' => ucfirst($value)], 'value' => $value]);
    }

    return $attribute;
}

function attributeValueOf(ProductAttribute $attribute, string $slug): ProductAttributeValue
{
    return $attribute->values()->where('slug', $slug)->sole();
}

function effectiveSlugs(Product $product): array
{
    return $product->defaultVariant()->fresh()->effectiveAttributeValues()
        ->mapWithKeys(fn (ProductAttributeValue $value): array => [$value->attribute->slug => $value->slug])
        ->all();
}

it('makes attributes describe the product only unless their scope says otherwise', function () {
    // Arrange
    $attribute = ProductAttribute::create(['slug' => 'brand-story', 'name' => 'Brand story']);

    // Act & Assert
    expect($attribute->fresh()->scope)->toBe(AttributeScope::Product);
});

it('inherits the product values of inherited attributes, but not of product-only ones', function () {
    // Arrange
    $material = scopedAttribute('material', AttributeScope::Inherited, ['cotton']);
    $care = scopedAttribute('care', AttributeScope::Product, ['hand-wash']);
    $product = Product::create(['name' => 'Shirt', 'price' => Price::of(1000)]);

    // Act
    $product->attributeValues()->attach([attributeValueOf($material, 'cotton')->id, attributeValueOf($care, 'hand-wash')->id]);

    // Assert
    expect(effectiveSlugs($product))->toBe(['material' => 'cotton'])
        ->and($product->defaultVariant()->attributeInheritance($material))->toBe(InheritanceState::Inherited);
});

it('overrides an inherited attribute per variant, with a value or with none', function () {
    // Arrange
    $material = scopedAttribute('material', AttributeScope::Inherited, ['cotton', 'polyester']);
    $origin = scopedAttribute('fit', AttributeScope::Inherited, ['regular']);
    $product = Product::create(['name' => 'Shirt', 'price' => Price::of(1000)]);
    $product->attributeValues()->attach([attributeValueOf($material, 'cotton')->id, attributeValueOf($origin, 'regular')->id]);
    $variant = $product->defaultVariant();

    // Act
    $variant->overrideAttribute($material, attributeValueOf($material, 'polyester'));
    $variant->overrideAttribute($origin);

    // Assert
    expect(effectiveSlugs($product))->toBe(['material' => 'polyester'])
        ->and($variant->attributeInheritance($material))->toBe(InheritanceState::Overridden)
        ->and($variant->attributeInheritance($origin))->toBe(InheritanceState::Overridden);
});

it('inherits again after an override', function () {
    // Arrange
    $material = scopedAttribute('material', AttributeScope::Inherited, ['cotton', 'polyester']);
    $product = Product::create(['name' => 'Shirt', 'price' => Price::of(1000)]);
    $product->attributeValues()->attach(attributeValueOf($material, 'cotton'));
    $variant = $product->defaultVariant();
    $variant->overrideAttribute($material, attributeValueOf($material, 'polyester'));

    // Act
    $variant->inheritAttribute($material);

    // Assert
    expect(effectiveSlugs($product))->toBe(['material' => 'cotton']);
});

it('gives variant-only attributes no product value to inherit', function () {
    // Arrange
    $length = scopedAttribute('length', AttributeScope::Variant, ['short', 'long']);
    $product = Product::create(['name' => 'Screw', 'price' => Price::of(10)]);
    $variant = $product->defaultVariant();

    // Act
    $variant->overrideAttribute($length, attributeValueOf($length, 'long'));

    // Assert
    expect(effectiveSlugs($product))->toBe(['length' => 'long']);
});

it('rejects variant values for product-only attributes', function () {
    // Arrange
    $care = scopedAttribute('care', AttributeScope::Product, ['hand-wash']);
    $variant = Product::create(['name' => 'Shirt', 'price' => Price::of(1000)])->defaultVariant();

    // Act & Assert
    expect(fn () => $variant->overrideAttribute($care, attributeValueOf($care, 'hand-wash')))->toThrow(InvalidInheritance::class);
});

it('replaces the product\'s whole list of a multi-value attribute with the variant\'s own values', function () {
    // Arrange
    $material = scopedAttribute('material', AttributeScope::Inherited, ['cotton', 'polyester', 'wool'], AttributeCardinality::Multiple);
    $product = Product::create(['name' => 'Shirt', 'price' => Price::of(1000)]);
    app(SetProductAttributeValues::class)->handle(ProductId::of($product), AttributeValueId::many([
        attributeValueOf($material, 'cotton')->id,
        attributeValueOf($material, 'polyester')->id,
    ]), origin());
    $variant = $product->defaultVariant();
    $slugs = fn (): array => $variant->fresh()->effectiveAttributeValues()->pluck('slug')->sort()->values()->all();

    // Act & Assert
    expect($slugs())->toBe(['cotton', 'polyester']);

    $variant->overrideAttribute($material, attributeValueOf($material, 'wool'), attributeValueOf($material, 'polyester'));
    expect($slugs())->toBe(['polyester', 'wool']);

    $variant->overrideAttribute($material);
    expect($slugs())->toBe([])
        ->and($variant->attributeInheritance($material))->toBe(InheritanceState::Overridden);

    $variant->inheritAttribute($material);
    expect($slugs())->toBe(['cotton', 'polyester']);
});

it('gives single-value attributes one value at most, on products and variants', function () {
    // Arrange
    $fit = scopedAttribute('fit', AttributeScope::Inherited, ['slim', 'regular']);
    $product = Product::create(['name' => 'Shirt', 'price' => Price::of(1000)]);

    // Act & Assert
    expect(fn () => app(SetProductAttributeValues::class)->handle(ProductId::of($product), AttributeValueId::many([
        attributeValueOf($fit, 'slim')->id,
        attributeValueOf($fit, 'regular')->id,
    ]), origin()))->toThrow(InvalidAttributeValues::class)
        ->and(fn () => $product->defaultVariant()->overrideAttribute($fit, attributeValueOf($fit, 'slim'), attributeValueOf($fit, 'regular')))
        ->toThrow(InvalidAttributeValues::class)
        ->and($product->attributeValues()->exists())->toBeFalse();
});
