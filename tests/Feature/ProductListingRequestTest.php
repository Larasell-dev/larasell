<?php

use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Http\Requests\ProductListingRequest;
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductOption;
use Larasell\Larasell\Price;
use Larasell\Larasell\Routing\ProductListingRoute;

beforeEach(function () {
    ProductListingRoute::get(function (ProductListingRequest $request): array {
        return $request->products()->pluck('slug')->all();
    }, prefix: 'c');
});

it('filters listing products by an option value slug', function () {
    $category = categoryForListing();
    $small = productForListing(['slug' => 'small-shirt'], $category);
    $large = productForListing(['slug' => 'large-shirt'], $category);
    $size = productOptionForListing('size');

    $small->optionValues()->attach($size->values()->create([
        'slug' => 'small',
        'name' => 'Small',
        'value' => 'small',
    ]));

    $large->optionValues()->attach($size->values()->create([
        'slug' => 'large',
        'name' => 'Large',
        'value' => 'large',
    ]));

    $this->getJson('/c/shirts?options[size]=small')
        ->assertOk()
        ->assertExactJson(['small-shirt']);
});

it('matches any selected values for the same option', function () {
    $category = categoryForListing();
    $small = productForListing(['slug' => 'small-shirt'], $category);
    $medium = productForListing(['slug' => 'medium-shirt'], $category);
    $large = productForListing(['slug' => 'large-shirt'], $category);
    $size = productOptionForListing('size');

    foreach ([
        [$small, 'small', 'Small'],
        [$medium, 'medium', 'Medium'],
        [$large, 'large', 'Large'],
    ] as [$product, $slug, $name]) {
        $product->optionValues()->attach($size->values()->create([
            'slug' => $slug,
            'name' => $name,
            'value' => $slug,
        ]));
    }

    $this->getJson('/c/shirts?options[size][]=small&options[size][]=medium')
        ->assertOk()
        ->assertExactJson(['medium-shirt', 'small-shirt']);
});

it('requires products to match every filtered option', function () {
    $category = categoryForListing();
    $matching = productForListing(['slug' => 'small-black-shirt'], $category);
    $wrongColor = productForListing(['slug' => 'small-white-shirt'], $category);
    $wrongSize = productForListing(['slug' => 'large-black-shirt'], $category);

    $size = productOptionForListing('size');
    $color = productOptionForListing('color');

    $small = $size->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'small']);
    $large = $size->values()->create(['slug' => 'large', 'name' => 'Large', 'value' => 'large']);
    $black = $color->values()->create(['slug' => 'black', 'name' => 'Black', 'value' => 'black']);
    $white = $color->values()->create(['slug' => 'white', 'name' => 'White', 'value' => 'white']);

    $matching->optionValues()->attach([$small->id, $black->id]);
    $wrongColor->optionValues()->attach([$small->id, $white->id]);
    $wrongSize->optionValues()->attach([$large->id, $black->id]);

    $this->getJson('/c/shirts?options[size]=small&options[color]=black')
        ->assertOk()
        ->assertExactJson(['small-black-shirt']);
});

it('ignores empty option filters', function () {
    $category = categoryForListing();
    productForListing(['slug' => 'shirt'], $category);

    $this->getJson('/c/shirts?options[size]=')
        ->assertOk()
        ->assertExactJson(['shirt']);
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

function productOptionForListing(string $slug, array $attributes = []): ProductOption
{
    return ProductOption::create(array_merge([
        'slug' => $slug,
        'name' => ucfirst($slug),
    ], $attributes));
}
