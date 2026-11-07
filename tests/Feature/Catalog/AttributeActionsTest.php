<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Catalog\Actions\CreateProductAttribute;
use Larasell\Larasell\Catalog\Actions\CreateProductAttributeValue;
use Larasell\Larasell\Catalog\Actions\DeleteProductAttribute;
use Larasell\Larasell\Catalog\Actions\DeleteProductAttributeValue;
use Larasell\Larasell\Catalog\Actions\UpdateProductAttribute;
use Larasell\Larasell\Catalog\Actions\UpdateProductAttributeValue;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Events\ProductAttributeCreated;
use Larasell\Larasell\Catalog\Events\ProductAttributeDeleted;
use Larasell\Larasell\Catalog\Events\ProductAttributeUpdated;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueCreated;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueDeleted;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueUpdated;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;
use Larasell\Larasell\Enums\ProductAttributeType;

it('writes attributes and their values with an event for each change', function () {
    Event::fake();

    $size = app(CreateProductAttribute::class)->handle(['slug' => 'size', 'name' => ['en' => 'Size']]);
    $small = app(CreateProductAttributeValue::class)->handle(AttributeId::of($size), ['slug' => 's', 'name' => ['en' => 'S'], 'value' => 's']);
    app(UpdateProductAttribute::class)->handle(AttributeId::of($size), ['name' => ['en' => 'Shoe size']]);
    app(UpdateProductAttributeValue::class)->handle(AttributeValueId::of($small), ['name' => ['en' => 'Small']]);
    app(DeleteProductAttributeValue::class)->handle(AttributeValueId::of($small));
    app(DeleteProductAttribute::class)->handle(AttributeId::of($size));

    expect(ProductAttribute::query()->count())->toBe(0)
        ->and(ProductAttributeValue::query()->count())->toBe(0);
    Event::assertDispatched(ProductAttributeCreated::class, fn (ProductAttributeCreated $event): bool => $event->attributeId->value === $size->id);
    Event::assertDispatched(ProductAttributeValueCreated::class, fn (ProductAttributeValueCreated $event): bool => $event->valueId->value === $small->id
        && $event->attributeId->value === $size->id);
    Event::assertDispatched(ProductAttributeUpdated::class, fn (ProductAttributeUpdated $event): bool => $event->changed === ['name']);
    Event::assertDispatched(ProductAttributeValueUpdated::class, fn (ProductAttributeValueUpdated $event): bool => $event->changed === ['name']);
    Event::assertDispatched(ProductAttributeValueDeleted::class, fn (ProductAttributeValueDeleted $event): bool => $event->valueId->value === $small->id);
    Event::assertDispatched(ProductAttributeDeleted::class, fn (ProductAttributeDeleted $event): bool => $event->attributeId->value === $size->id);
});

it('checks attribute values against their attribute type before writing', function () {
    $weight = app(CreateProductAttribute::class)->handle(['slug' => 'weight', 'name' => ['en' => 'Weight'], 'type' => ProductAttributeType::Number]);

    expect(fn () => app(CreateProductAttributeValue::class)->handle(AttributeId::of($weight), ['slug' => 'heavy', 'name' => ['en' => 'Heavy'], 'value' => 'heavy']))
        ->toThrow(InvalidArgumentException::class)
        ->and(ProductAttributeValue::query()->count())->toBe(0);
});
