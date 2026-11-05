<?php

use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Http\Requests\ProductListingRequest;
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Price;

beforeEach(function () {
    config()->set('queue.default', 'sync');
    config()->set('larasell.locale.supported', ['en', 'de']);

    Route::storefront(fn () => Route::seo([
        Category::class => fn (ProductListingRequest $request): array => $request->products()->get()->map(
            fn (Product $product): string => (string) $product->sku,
        )->all(),
    ]));
});

it('filters listing products by an attribute value slug', function () {
    $category = categoryForListing();
    $small = productForListing(['sku' => 'small-shirt'], $category);
    $large = productForListing(['sku' => 'large-shirt'], $category);
    $size = productAttributeForListing('size');

    $small->attributeValues()->attach($size->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'small',
    ]));

    $large->attributeValues()->attach($size->values()->create([
        'slug' => 'large',
        'name' => 'Large',
        'value' => 'large',
    ]));

    $this->getJson('/en/shirts?attributes[size]=small')
        ->assertOk()
        ->assertExactJson(['small-shirt']);
});

it('matches any selected values for the same attribute', function () {
    $category = categoryForListing();
    $small = productForListing(['sku' => 'small-shirt'], $category);
    $medium = productForListing(['sku' => 'medium-shirt'], $category);
    $large = productForListing(['sku' => 'large-shirt'], $category);
    $size = productAttributeForListing('size');

    foreach ([
        [$small, 'small', 'Small'],
        [$medium, 'medium', 'Medium'],
        [$large, 'large', 'Large'],
    ] as [$product, $slug, $name]) {
        $product->attributeValues()->attach($size->values()->create([
            'slug' => $slug,
            'name' => $name,
            'value' => $slug,
        ]));
    }

    $this->getJson('/en/shirts?attributes[size][]=small&attributes[size][]=medium')
        ->assertOk()
        ->assertExactJson(['medium-shirt', 'small-shirt']);
});

it('requires products to match every filtered attribute', function () {
    $category = categoryForListing();
    $matching = productForListing(['sku' => 'small-black-shirt'], $category);
    $wrongColor = productForListing(['sku' => 'small-white-shirt'], $category);
    $wrongSize = productForListing(['sku' => 'large-black-shirt'], $category);

    $size = productAttributeForListing('size');
    $color = productAttributeForListing('color');

    $small = $size->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'small']);
    $large = $size->values()->create(['slug' => 'large', 'name' => 'Large', 'value' => 'large']);
    $black = $color->values()->create(['slug' => 'black', 'name' => 'Black', 'value' => 'black']);
    $white = $color->values()->create(['slug' => 'white', 'name' => 'White', 'value' => 'white']);

    $matching->attributeValues()->attach([$small->id, $black->id]);
    $wrongColor->attributeValues()->attach([$small->id, $white->id]);
    $wrongSize->attributeValues()->attach([$large->id, $black->id]);

    $this->getJson('/en/shirts?attributes[size]=small&attributes[color]=black')
        ->assertOk()
        ->assertExactJson(['small-black-shirt']);
});

it('ignores empty attribute filters', function () {
    $category = categoryForListing();
    productForListing(['sku' => 'shirt'], $category);

    $this->getJson('/en/shirts?attributes[size]=')
        ->assertOk()
        ->assertExactJson(['shirt']);
});

it('sorts products by their name in the current locale', function () {
    $category = categoryForListing(['name' => ['en' => 'Shirts', 'de' => 'Hemden']]);
    productForListing(['sku' => 'lamp', 'name' => ['en' => 'Lamp', 'de' => 'Z Lampe']], $category);
    productForListing(['sku' => 'table', 'name' => ['en' => 'Table', 'de' => 'A Tisch']], $category);

    $this->getJson('/de/hemden')
        ->assertOk()
        ->assertExactJson(['table', 'lamp']);
});

it('accepts name as an explicit sort option', function () {
    $category = categoryForListing();
    productForListing(['sku' => 'z-lamp', 'name' => 'Z Lamp'], $category);
    productForListing(['sku' => 'a-table', 'name' => 'A Table'], $category);

    $this->getJson('/en/shirts?sort=name')
        ->assertOk()
        ->assertExactJson(['a-table', 'z-lamp']);
});

it('lists the products of the category at a nested URL in the current locale', function () {
    $clothes = categoryForListing(['name' => ['en' => 'Clothes', 'de' => 'Kleidung']]);
    $shirts = categoryForListing(['parent_id' => $clothes->getKey(), 'name' => ['en' => 'Shirts', 'de' => 'Hemden']]);
    productForListing(['sku' => 'linen-shirt'], $shirts);
    productForListing(['sku' => 'jacket'], categoryForListing(['name' => 'Jackets']));

    $this->getJson('/de/kleidung/hemden')
        ->assertOk()
        ->assertExactJson(['linen-shirt']);
    $this->getJson('/en/clothes')
        ->assertOk()
        ->assertExactJson(['linen-shirt']);
});

function categoryForListing(array $attributes = []): Category
{
    return Category::create(array_merge([
        'name' => 'Shirts',
    ], $attributes));
}

function productForListing(array $attributes, Category $category): Product
{
    $attributes = array_merge([
        'sku' => fake()->unique()->slug(),
    ], $attributes);

    $product = Product::create(array_merge([
        'name' => $attributes['sku'],
        'price' => Price::of(1000),
    ], $attributes));

    $product->categories()->attach($category);

    return $product;
}

function productAttributeForListing(string $slug, array $attributes = []): ProductAttribute
{
    return ProductAttribute::create(array_merge([
        'slug' => $slug,
        'name' => ucfirst($slug),
    ], $attributes));
}
