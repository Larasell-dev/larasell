<?php

use Illuminate\Database\QueryException;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Models\ProductAttributeValue;
use Larasell\Larasell\Models\ProductVariant;
use Larasell\Larasell\Price;

it('creates a variant from a valid combination of product attribute values', function () {
    [$product, $small, $black] = variantProduct();

    $variant = $product->createVariant([$black, $small], [
        'sku' => 'TSHIRT-BLK-S',
        'barcode' => '04012345678901',
        'price' => Price::of(1299),
        'stock' => 5,
    ]);

    expect($variant)->toBeInstanceOf(ProductVariant::class)
        ->and($variant->product->is($product))->toBeTrue()
        ->and($variant->attributeValues)->toHaveCount(2)
        ->and($variant->attributeValues->modelKeys())->toEqualCanonicalizing([$small->id, $black->id])
        ->and($variant->combination_key)->not->toBeEmpty();
});

it('rejects a variant containing two values from the same attribute', function () {
    [$product, $small] = variantProduct();
    $medium = $small->attribute->values()->create([
        'slug' => 'medium',
        'name' => 'Medium',
        'value' => 'medium',
    ]);
    $product->attributeValues()->attach($medium);

    expect(fn () => $product->createVariant([$small, $medium]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a variant containing a value unavailable to its product', function () {
    [$product, $small] = variantProduct();
    $material = variantAttribute('material');
    $cotton = variantValue($material, 'cotton');

    expect(fn () => $product->createVariant([$small, $cotton]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects duplicate attribute combinations regardless of value order', function () {
    [$product, $small, $black] = variantProduct();

    $first = $product->createVariant([$small, $black]);

    expect(fn () => $product->createVariant([$black, $small]))
        ->toThrow(QueryException::class)
        ->and($first->combination_key)->not->toBeEmpty();
});

it('requires non-null variant identifiers to be unique', function (string $column) {
    [$firstProduct, $small, $black] = variantProduct();
    $firstProduct->createVariant([$small, $black], [$column => 'DUPLICATE']);

    [$secondProduct, $medium, $white] = variantProduct('second-shirt');

    expect(fn () => $secondProduct->createVariant([$medium, $white], [$column => 'DUPLICATE']))
        ->toThrow(QueryException::class);
})->with(['sku', 'barcode']);

it('resolves a variant independently of selection order', function () {
    [$product, $small, $black] = variantProduct();
    $variant = $product->createVariant([$small, $black]);

    $resolved = $product->variantFor([
        'color' => 'black',
        'size' => 'small',
    ]);
    $reordered = $product->variantFor([
        'size' => 'small',
        'color' => 'black',
    ]);

    expect($resolved->is($variant))->toBeTrue()
        ->and($reordered->is($variant))->toBeTrue();
});

it('rejects incomplete variant selections', function () {
    [$product, $small, $black] = variantProduct();
    $product->createVariant([$small, $black]);

    expect(fn () => $product->variantFor(['size' => 'small']))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects selections without a matching variant', function () {
    [$product, $small, $black] = variantProduct();
    $white = variantValue($black->attribute, 'white');
    $product->attributeValues()->attach($white);
    $product->createVariant([$small, $black]);

    expect(fn () => $product->variantFor([
        'size' => 'small',
        'color' => 'white',
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects selections resolving to an unavailable variant', function () {
    [$product, $small, $black] = variantProduct();
    $product->createVariant([$small, $black], ['status' => Visibility::Hidden]);

    expect(fn () => $product->variantFor([
        'size' => 'small',
        'color' => 'black',
    ]))->toThrow(InvalidArgumentException::class);
});

it('exposes visible variants with the parent product already set', function () {
    [$product, $small, $black] = variantProduct();
    $white = variantValue($black->attribute, 'white');
    $product->attributeValues()->attach($white);
    $visible = $product->createVariant([$small, $black], ['position' => 0]);
    $product->createVariant([$small, $white], [
        'status' => Visibility::Hidden,
        'position' => 1,
    ]);

    $product->load('visibleVariants');

    expect($product->visibleVariants)->toHaveCount(1)
        ->and($product->visibleVariants->sole()->is($visible))->toBeTrue()
        ->and($product->visibleVariants->sole()->relationLoaded('product'))->toBeTrue()
        ->and($product->visibleVariants->sole()->product)->toBe($product)
        ->and($product->visibleVariants->sole()->name())->toBe('Small / Black');
});

/**
 * @return array{Product, ProductAttributeValue, ProductAttributeValue}
 */
function variantProduct(?string $slug = null): array
{
    $product = Product::create([
        'slug' => $slug ?? fake()->unique()->slug(),
        'name' => fake()->words(3, true),
        'price' => Price::of(1000),
    ]);
    $size = variantAttribute('size', 'Size');
    $small = variantValue($size, 'small');
    $color = variantAttribute('color', 'Color');
    $black = variantValue($color, 'black');

    $product->attributeValues()->attach([$small->id, $black->id]);
    $product->variantDimensions()->sync([
        $size->id => ['position' => 0],
        $color->id => ['position' => 1],
    ]);

    return [$product, $small, $black];
}

function variantAttribute(string $slug, ?string $name = null): ProductAttribute
{
    return ProductAttribute::firstOrCreate(
        ['slug' => $slug],
        ['name' => $name ?? ucfirst($slug)],
    );
}

function variantValue(ProductAttribute $attribute, string $slug): ProductAttributeValue
{
    return $attribute->values()->firstOrCreate(
        ['slug' => $slug],
        [
            'name' => ucfirst($slug),
            'value' => $slug,
        ],
    );
}

it('generates the cartesian product of selected attribute dimensions', function () {
    $product = variantGenerationProduct();
    $size = ProductAttribute::query()->where('slug', 'size')->sole();
    $color = ProductAttribute::query()->where('slug', 'color')->sole();

    $variants = $product->generateVariants([$size, $color]);

    expect($variants)->toHaveCount(4)
        ->and($product->variantDimensions->modelKeys())->toBe([$size->id, $color->id])
        ->and($variants->every(fn (ProductVariant $variant): bool => $variant->status === Visibility::Hidden))->toBeTrue()
        ->and($variants->map->attributeValues->flatten()->pluck('slug')->contains('cotton'))->toBeFalse();
});

it('requires variant generation to produce at least two combinations', function (array $dimensions) {
    $product = variantGenerationProduct();

    expect(fn () => $product->generateVariants(array_map(
        fn (string $slug): ProductAttribute => ProductAttribute::query()->where('slug', $slug)->sole(),
        $dimensions,
    )))->toThrow(InvalidArgumentException::class);
})->with([
    'no dimensions' => [[]],
    'one value' => [['material']],
]);

it('generates only missing variants without changing existing variants', function () {
    $product = variantGenerationProduct();
    $size = ProductAttribute::query()->where('slug', 'size')->sole();
    $color = ProductAttribute::query()->where('slug', 'color')->sole();
    $initial = $product->generateVariants([$size, $color]);
    $initial->first()->update(['sku' => 'KEEP-ME']);

    $large = variantValue($size, 'large');
    $product->attributeValues()->attach($large);
    $regenerated = $product->generateVariants([$size, $color]);

    expect($regenerated)->toHaveCount(6)
        ->and($product->variants()->count())->toBe(6)
        ->and($initial->first()->fresh()->sku)->toBe('KEEP-ME');
});

it('does not silently change dimensions after variants exist', function () {
    $product = variantGenerationProduct();
    $size = ProductAttribute::query()->where('slug', 'size')->sole();
    $color = ProductAttribute::query()->where('slug', 'color')->sole();
    $material = ProductAttribute::query()->where('slug', 'material')->sole();
    $product->generateVariants([$size, $color]);

    expect(fn () => $product->generateVariants([$size, $material]))
        ->toThrow(InvalidArgumentException::class);
});

function variantGenerationProduct(): Product
{
    $product = Product::create([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(3, true),
        'price' => Price::of(1000),
    ]);
    $size = variantAttribute('size', 'Size');
    $color = variantAttribute('color', 'Color');
    $material = variantAttribute('material', 'Material');

    $product->attributeValues()->attach([
        variantValue($size, 'small')->id,
        variantValue($size, 'medium')->id,
        variantValue($color, 'black')->id,
        variantValue($color, 'white')->id,
        variantValue($material, 'cotton')->id,
    ]);

    return $product;
}
