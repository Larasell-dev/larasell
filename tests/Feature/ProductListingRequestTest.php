<?php

use Illuminate\Support\Facades\App;
use Larasell\Larasell\Http\Requests\ProductListingRequest;
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Price;
use Larasell\Larasell\Routing\ProductListingRoute;

beforeEach(function () {
    ProductListingRoute::get(function (ProductListingRequest $request): array {
        return $request->products()->get()->map(
            fn (Product $product): string => $product->slug->get(),
        )->all();
    }, prefix: 'c');
});

it('filters listing products by an attribute value slug', function () {
    $category = categoryForListing();
    $small = productForListing(['slug' => 'small-shirt'], $category);
    $large = productForListing(['slug' => 'large-shirt'], $category);
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

    $this->getJson('/c/shirts?attributes[size]=small')
        ->assertOk()
        ->assertExactJson(['small-shirt']);
});

it('matches any selected values for the same attribute', function () {
    $category = categoryForListing();
    $small = productForListing(['slug' => 'small-shirt'], $category);
    $medium = productForListing(['slug' => 'medium-shirt'], $category);
    $large = productForListing(['slug' => 'large-shirt'], $category);
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

    $this->getJson('/c/shirts?attributes[size][]=small&attributes[size][]=medium')
        ->assertOk()
        ->assertExactJson(['medium-shirt', 'small-shirt']);
});

it('requires products to match every filtered attribute', function () {
    $category = categoryForListing();
    $matching = productForListing(['slug' => 'small-black-shirt'], $category);
    $wrongColor = productForListing(['slug' => 'small-white-shirt'], $category);
    $wrongSize = productForListing(['slug' => 'large-black-shirt'], $category);

    $size = productAttributeForListing('size');
    $color = productAttributeForListing('color');

    $small = $size->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'small']);
    $large = $size->values()->create(['slug' => 'large', 'name' => 'Large', 'value' => 'large']);
    $black = $color->values()->create(['slug' => 'black', 'name' => 'Black', 'value' => 'black']);
    $white = $color->values()->create(['slug' => 'white', 'name' => 'White', 'value' => 'white']);

    $matching->attributeValues()->attach([$small->id, $black->id]);
    $wrongColor->attributeValues()->attach([$small->id, $white->id]);
    $wrongSize->attributeValues()->attach([$large->id, $black->id]);

    $this->getJson('/c/shirts?attributes[size]=small&attributes[color]=black')
        ->assertOk()
        ->assertExactJson(['small-black-shirt']);
});

it('ignores empty attribute filters', function () {
    $category = categoryForListing();
    productForListing(['slug' => 'shirt'], $category);

    $this->getJson('/c/shirts?attributes[size]=')
        ->assertOk()
        ->assertExactJson(['shirt']);
});

it('sorts products by their name in the current locale', function () {
    App::setLocale('de');
    $category = categoryForListing();
    productForListing(['slug' => 'lamp', 'name' => ['en' => 'Lamp', 'de' => 'Z Lampe']], $category);
    productForListing(['slug' => 'table', 'name' => ['en' => 'Table', 'de' => 'A Tisch']], $category);

    $this->getJson('/c/shirts')
        ->assertOk()
        ->assertExactJson(['table', 'lamp']);
});

it('accepts name as an explicit sort option', function () {
    $category = categoryForListing();
    productForListing(['slug' => 'z-lamp', 'name' => 'Z Lamp'], $category);
    productForListing(['slug' => 'a-table', 'name' => 'A Table'], $category);

    $this->getJson('/c/shirts?sort=name')
        ->assertOk()
        ->assertExactJson(['a-table', 'z-lamp']);
});

it('resolves a category by its slug in the current locale', function () {
    App::setLocale('de');
    $category = categoryForListing([
        'slug' => ['en' => 'shirts', 'de' => 'hemden'],
    ]);
    productForListing(['slug' => 'linen-shirt'], $category);

    $this->getJson('/c/hemden')
        ->assertOk()
        ->assertExactJson(['linen-shirt']);
});

function categoryForListing(array $attributes = []): Category
{
    return Category::create(array_merge([
        'slug' => 'shirts',
        'name' => 'Shirts',
    ], $attributes));
}

function productForListing(array $attributes, Category $category): Product
{
    $attributes = array_merge([
        'slug' => fake()->unique()->slug(),
    ], $attributes);

    $product = Product::create(array_merge([
        'name' => $attributes['slug'],
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
