<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Actions\CreateProductPayload;
use Larasell\Larasell\Catalog\Actions\DeleteProduct;
use Larasell\Larasell\Catalog\Actions\DeleteProductVariant;
use Larasell\Larasell\Catalog\Actions\SetProductAttributeValues;
use Larasell\Larasell\Catalog\Actions\SetProductCategories;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;
use Larasell\Larasell\Catalog\Actions\UpdateProductPayload;
use Larasell\Larasell\Catalog\Actions\UpdateProductVariant;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\ProductCreated;
use Larasell\Larasell\Catalog\Events\ProductDeleted;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Events\ProductVariantDeleted;
use Larasell\Larasell\Catalog\Events\ProductVariantUpdated;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Catalog\Models\ProductVariant;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\VariantId;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;

function createProduct(string $name = 'Boots', array $attributes = []): Product
{
    return app(CreateProduct::class)->handle(new CreateProductPayload(['name' => ['en' => $name], 'price' => Price::of(1000), ...$attributes]), origin());
}

/** What an app passes along with a product action for its own listeners. */
final readonly class ProductNote
{
    public function __construct(public string $text) {}
}

it('creates a product with its default variant and announces it by ID', function () {
    Event::fake([ProductCreated::class]);

    $product = createProduct('Boots');

    expect(Product::query()->findOrFail($product->id)->name->get('en'))->toBe('Boots')
        ->and($product->defaultVariant()->is_default)->toBeTrue();
    Event::assertDispatched(ProductCreated::class, fn (ProductCreated $event): bool => $event->productId->value === $product->id);
});

it('dispatches catalog events inside the write, so a failing listener undoes it', function () {
    Event::listen(ProductCreated::class, fn () => throw new RuntimeException('Listener failed.'));

    expect(fn () => createProduct('Boots'))->toThrow(RuntimeException::class, 'Listener failed.')
        ->and(Product::query()->count())->toBe(0)
        ->and(ProductVariant::query()->count())->toBe(0);
});

it('updates the stored product, not a stale copy, and names the changed attributes', function () {
    $product = createProduct('Boots', ['status' => Visibility::Visible]);
    $stale = Product::query()->findOrFail($product->id);
    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(['status' => Visibility::Hidden]), origin());
    Event::fake([ProductUpdated::class]);

    $updated = app(UpdateProduct::class)->handle(ProductId::of($stale), new UpdateProductPayload(['name' => ['en' => 'Leather Boots']]), origin());

    expect($updated->status)->toBe(Visibility::Hidden)
        ->and($updated->name->get('en'))->toBe('Leather Boots')
        ->and($updated->defaultVariant()->isVisible())->toBeFalse();
    Event::assertDispatched(ProductUpdated::class, fn (ProductUpdated $event): bool => $event->productId->value === $product->id
        && $event->changed === ['name']);
});

it('announces nothing when an update changes nothing', function () {
    $product = createProduct('Boots');
    Event::fake([ProductUpdated::class]);

    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(['name' => ['en' => 'Boots']]), origin());

    Event::assertNotDispatched(ProductUpdated::class);
});

it('announces a rename to a name that only looks equal as a number', function (string $before, string $after) {
    $product = createProduct($before);
    Event::fake([ProductUpdated::class]);

    $updated = app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(['name' => ['en' => $after]]), origin());

    expect($updated->fresh()?->name->get('en'))->toBe($after);
    Event::assertDispatched(ProductUpdated::class, fn (ProductUpdated $event): bool => $event->changed === ['name']);
})->with([
    'exponent' => ['10', '1e1'],
    'leading zero' => ['1', '01'],
    'decimal' => ['1', '1.0'],
]);

it('refuses to update a product that was deleted meanwhile', function () {
    $product = createProduct('Boots');
    app(DeleteProduct::class)->handle(ProductId::of($product), origin());

    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(['name' => ['en' => 'Leather Boots']]), origin());
})->throws(ModelNotFoundException::class);

it('deletes a product and announces what listeners need to know about it', function () {
    $shoes = Category::query()->create(['name' => ['en' => 'Shoes']]);
    $product = createProduct('Boots');
    app(SetProductCategories::class)->handle(ProductId::of($product), [CategoryId::of($shoes)], origin());
    $variantId = $product->defaultVariant()->id;
    Event::fake([ProductDeleted::class]);

    app(DeleteProduct::class)->handle(ProductId::of($product), origin());

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
    Event::assertDispatched(ProductDeleted::class, fn (ProductDeleted $event): bool => $event->productId->value === $product->id
        && $event->name->get('en') === 'Boots'
        && CategoryId::values($event->categoryIds) === [$shoes->id]
        && VariantId::values($event->variantIds) === [$variantId]);
});

it('keeps the product when a listener of its deletion fails', function () {
    $product = createProduct('Boots');
    Event::listen(ProductDeleted::class, fn () => throw new RuntimeException('Listener failed.'));

    expect(fn () => app(DeleteProduct::class)->handle(ProductId::of($product), origin()))->toThrow(RuntimeException::class)
        ->and(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('sets the categories and attribute values of a product', function () {
    $product = createProduct('Boots');
    $shoes = Category::query()->create(['name' => ['en' => 'Shoes']]);
    $sale = Category::query()->create(['name' => ['en' => 'Sale']]);
    $size = ProductAttribute::query()->create(['slug' => 'size', 'name' => ['en' => 'Size']]);
    $small = $size->values()->create(['slug' => 's', 'name' => ['en' => 'S'], 'value' => 's']);
    $changes = [];
    Event::listen(ProductUpdated::class, function (ProductUpdated $event) use (&$changes): void {
        $changes[] = $event->changed;
    });

    app(SetProductCategories::class)->handle(ProductId::of($product), [CategoryId::of($shoes), CategoryId::of($sale)], origin());
    app(SetProductCategories::class)->handle(ProductId::of($product), [CategoryId::of($sale), CategoryId::of($shoes)], origin());
    app(SetProductAttributeValues::class)->handle(ProductId::of($product), [AttributeValueId::of($small)], origin());

    $product = Product::query()->findOrFail($product->id);
    expect($product->categories->modelKeys())->toEqualCanonicalizing([$shoes->id, $sale->id])
        ->and($product->attributeValues->modelKeys())->toBe([$small->id])
        ->and($changes)->toBe([['categories'], ['attribute_values']]);
});

it('updates and deletes variants with an event for each change', function () {
    // Arrange
    $product = createProduct('Shirt');
    [$small, $large] = optionVariants(Product::query()->findOrFail($product->id), [optionDefinition('size', ['s', 'l'])]);
    Event::fake([ProductVariantUpdated::class, ProductVariantDeleted::class]);

    // Act
    app(UpdateProductVariant::class)->handle(VariantId::of($small), ['sku' => 'S-1', 'status' => Visibility::Hidden]);
    app(DeleteProductVariant::class)->handle(VariantId::of($large));

    // Assert
    expect(ProductVariant::query()->where('product_id', $product->id)->pluck('sku')->all())->toBe(['S-1']);
    Event::assertDispatched(ProductVariantUpdated::class, fn (ProductVariantUpdated $event): bool => $event->variantId->equals(VariantId::of($small))
        && $event->productId->value === $product->id && $event->changed === ['sku', 'status']);
    Event::assertDispatched(ProductVariantDeleted::class, fn (ProductVariantDeleted $event): bool => $event->variantId->equals(VariantId::of($large)));
});

it('hands what the app passed along to the product\'s events, even when nothing else changed', function () {
    Event::fake([ProductCreated::class, ProductUpdated::class]);

    $product = app(CreateProduct::class)->handle(new CreateProductPayload(
        ['name' => ['en' => 'Boots'], 'price' => Price::of(1000)],
        new Attachments(new ProductNote('new')),
    ), origin());
    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(attachments: new Attachments(new ProductNote('edited'))), origin());

    Event::assertDispatched(ProductCreated::class, fn (ProductCreated $event): bool => $event->attachments->get(ProductNote::class)?->text === 'new');
    Event::assertDispatched(ProductUpdated::class, fn (ProductUpdated $event): bool => $event->changed === []
        && $event->attachments->get(ProductNote::class)?->text === 'edited');
});

it('announces no product update when nothing changed and nothing was passed along', function () {
    $product = createProduct('Boots');
    Event::fake([ProductUpdated::class]);

    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(['name' => ['en' => 'Boots']]), origin());

    Event::assertNotDispatched(ProductUpdated::class);
});
