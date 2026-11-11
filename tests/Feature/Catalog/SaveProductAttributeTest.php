<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Catalog\Actions\AttributeValueDraft;
use Larasell\Larasell\Catalog\Actions\SaveProductAttribute;
use Larasell\Larasell\Catalog\Actions\SaveProductAttributePayload;
use Larasell\Larasell\Catalog\AttributeCardinality;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\AttributeScope;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueCreated;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueDeleted;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueUpdated;
use Larasell\Larasell\Catalog\Exceptions\InvalidAttributeValues;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;
use Larasell\Larasell\Enums\ProductAttributeType;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

function draft(?ProductAttributeValue $value, string $name, string $slug): AttributeValueDraft
{
    return new AttributeValueDraft($value ? AttributeValueId::of($value) : null, new Translatable(['en' => $name]), $slug);
}

function payload(array $values, string $name = 'Material', ProductAttributeType $type = ProductAttributeType::Text): SaveProductAttributePayload
{
    return new SaveProductAttributePayload(new Translatable(['en' => $name]), 'material', $type, AttributeScope::Inherited, AttributeCardinality::Single, $values);
}

it('creates an attribute with its values in the given order', function () {
    // Act
    $attribute = app(SaveProductAttribute::class)->handle(null, payload([draft(null, 'Wool', 'wool'), draft(null, 'Cotton', 'cotton')]));

    // Assert
    expect($attribute->type)->toBe(ProductAttributeType::Text)
        ->and($attribute->values()->orderBy('position')->pluck('slug')->all())->toBe(['wool', 'cotton']);
});

it('updates, creates, reorders and deletes values to match the drafts', function () {
    // Arrange
    $attribute = ProductAttribute::query()->create(['slug' => 'material', 'name' => ['en' => 'Material'], 'type' => ProductAttributeType::Text]);
    $cotton = $attribute->values()->create(['slug' => 'cotton', 'name' => ['en' => 'Cotton'], 'value' => 'Cotton', 'position' => 1]);
    $wool = $attribute->values()->create(['slug' => 'wool', 'name' => ['en' => 'Wool'], 'value' => 'Wool', 'position' => 2]);
    $silk = $attribute->values()->create(['slug' => 'silk', 'name' => ['en' => 'Silk'], 'value' => 'Silk', 'position' => 3]);
    $product = Product::create(['name' => 'Shirt', 'price' => Price::of(1000)]);
    $product->attributeValues()->attach([$cotton->id, $silk->id]);
    Event::fake([ProductAttributeValueCreated::class, ProductAttributeValueUpdated::class, ProductAttributeValueDeleted::class]);

    // Act
    app(SaveProductAttribute::class)->handle(AttributeId::of($attribute), payload([
        draft($wool, 'Merino wool', 'merino'),
        draft(null, 'Linen', 'linen'),
        draft($cotton, 'Cotton', 'cotton'),
    ], 'Fabric'));

    // Assert
    expect($attribute->fresh()->name->get('en'))->toBe('Fabric')
        ->and($attribute->values()->orderBy('position')->get()->map(fn ($value) => [$value->slug, $value->name->get('en'), $value->position])->all())
        ->toBe([['merino', 'Merino wool', 1], ['linen', 'Linen', 2], ['cotton', 'Cotton', 3]])
        // The product keeps cotton and loses the deleted silk.
        ->and($product->attributeValues()->pluck('slug')->all())->toBe(['cotton']);
    Event::assertDispatched(ProductAttributeValueDeleted::class, fn ($event): bool => $event->valueId->value === $silk->id);
    Event::assertDispatchedTimes(ProductAttributeValueCreated::class, 1);
});

it('refuses a draft of another attribute\'s value and changes nothing', function () {
    // Arrange
    $material = ProductAttribute::query()->create(['slug' => 'material', 'name' => ['en' => 'Material'], 'type' => ProductAttributeType::Text]);
    $cotton = $material->values()->create(['slug' => 'cotton', 'name' => ['en' => 'Cotton'], 'value' => 'Cotton']);
    $care = ProductAttribute::query()->create(['slug' => 'care', 'name' => ['en' => 'Care'], 'type' => ProductAttributeType::Text]);
    $wash = $care->values()->create(['slug' => 'wash', 'name' => ['en' => 'Wash'], 'value' => 'Wash']);

    // Act & Assert
    expect(fn () => app(SaveProductAttribute::class)->handle(AttributeId::of($material), payload([draft($wash, 'Wash', 'wash')], 'Fabric')))
        ->toThrow(InvalidAttributeValues::class);
    expect($material->fresh()->name->get('en'))->toBe('Material')
        ->and($cotton->fresh())->not->toBeNull()
        ->and($wash->fresh()->product_attribute_id)->toBe($care->id);
});

it('swaps the slugs of two values in one save, with one slug change each', function () {
    // Arrange
    $attribute = ProductAttribute::query()->create(['slug' => 'material', 'name' => ['en' => 'Material'], 'type' => ProductAttributeType::Text]);
    $cotton = $attribute->values()->create(['slug' => 'a', 'name' => ['en' => 'Cotton'], 'value' => 'Cotton', 'position' => 1]);
    $wool = $attribute->values()->create(['slug' => 'b', 'name' => ['en' => 'Wool'], 'value' => 'Wool', 'position' => 2]);
    Event::fake([ProductAttributeValueUpdated::class]);

    // Act
    app(SaveProductAttribute::class)->handle(AttributeId::of($attribute), payload([
        draft($cotton, 'Cotton', 'b'),
        draft($wool, 'Wool', 'a'),
        // A new value takes the slug another one gives up in the same save.
        draft(null, 'Silk', 'c'),
    ]));

    // Assert
    expect($cotton->fresh()->slug)->toBe('b')
        ->and($wool->fresh()->slug)->toBe('a');
    Event::assertDispatchedTimes(ProductAttributeValueUpdated::class, 2);
    Event::assertDispatched(ProductAttributeValueUpdated::class, fn ($event): bool => $event->valueId->value === $cotton->id && $event->changed === ['slug']);
});

it('creates a yes-or-no attribute with its yes and no, which can be renamed but not added to', function () {
    // Arrange
    $save = app(SaveProductAttribute::class);

    // Act
    $attribute = $save->handle(null, payload([draft(null, 'Yes', '__boolean_true'), draft(null, 'No', '__boolean_false')], 'Waterproof', ProductAttributeType::Boolean));
    [$yes, $no] = $attribute->values()->orderBy('position')->get()->all();
    $save->handle(AttributeId::of($attribute), payload([draft($yes, 'Waterproof', '__boolean_true'), draft($no, 'Not waterproof', '__boolean_false')], 'Waterproof', ProductAttributeType::Boolean));

    // Assert
    expect([$yes->fresh()->value, $yes->fresh()->name->get('en'), $no->fresh()->value])->toBe([true, 'Waterproof', false])
        ->and(fn () => $save->handle(AttributeId::of($attribute), payload([draft($yes, 'Yes', '__boolean_true'), draft($no, 'No', '__boolean_false'), draft(null, 'Maybe', 'maybe')], 'Waterproof', ProductAttributeType::Boolean)))
        ->toThrow(InvalidAttributeValues::class)
        ->and(fn () => $save->handle(AttributeId::of($attribute), payload([draft($yes, 'Yes', '__boolean_true')], 'Waterproof', ProductAttributeType::Boolean)))
        ->toThrow(InvalidAttributeValues::class)
        ->and($attribute->values()->count())->toBe(2);
});

it('keeps a number value\'s number in step with its name, and refuses one that isn\'t a number', function () {
    // Arrange
    $save = app(SaveProductAttribute::class);
    $attribute = $save->handle(null, payload([draft(null, '180', 'g180')], 'Weight', ProductAttributeType::Number));
    $value = $attribute->values()->sole();

    // Act
    $save->handle(AttributeId::of($attribute), payload([draft($value, '2.5', 'g180')], 'Weight', ProductAttributeType::Number));

    // Assert
    expect($value->fresh()->value)->toBe(2.5)
        ->and(fn () => $save->handle(AttributeId::of($attribute), payload([draft($value, 'heavy', 'g180')], 'Weight', ProductAttributeType::Number)))
        ->toThrow(InvalidAttributeValues::class)
        ->and(fn () => $save->handle(AttributeId::of($attribute), payload([draft($value, '3', 'g180')], 'Weight')))
        ->toThrow(InvalidAttributeValues::class)
        ->and($value->fresh()->value)->toBe(2.5);
});
