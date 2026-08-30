<?php

use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

it('queries products directly assigned to a category', function () {
    $parent = categoryForProductScope('food');
    $child = categoryForProductScope('wraps', $parent);
    $parentProduct = productForCategoryScope('food-product', $parent);
    productForCategoryScope('wrap-product', $child);

    $products = Product::query()->inCategory($parent)->get();

    expect($products)->toHaveCount(1)
        ->and($products->first()->is($parentProduct))->toBeTrue();
});

it('queries products assigned anywhere in a category tree', function () {
    $parent = categoryForProductScope('food');
    $child = categoryForProductScope('wraps', $parent);
    $grandchild = categoryForProductScope('chicken-wraps', $child);
    $unrelated = categoryForProductScope('drinks');

    $parentProduct = productForCategoryScope('food-product', $parent);
    $childProduct = productForCategoryScope('wrap-product', $child);
    $grandchildProduct = productForCategoryScope('chicken-wrap-product', $grandchild);
    productForCategoryScope('drink-product', $unrelated);

    $products = Product::query()->inCategoryTree($parent)->get();

    expect($products)->toHaveCount(3)
        ->and($products->modelKeys())->toEqualCanonicalizing([
            $parentProduct->getKey(),
            $childProduct->getKey(),
            $grandchildProduct->getKey(),
        ]);
});

function categoryForProductScope(string $slug, ?Category $parent = null): Category
{
    return Category::create([
        'parent_id' => $parent?->getKey(),
        'slug' => $slug,
        'name' => str($slug)->headline()->toString(),
    ]);
}

function productForCategoryScope(string $slug, Category $category): Product
{
    $product = Product::create([
        'slug' => $slug,
        'name' => str($slug)->headline()->toString(),
        'price' => Price::of(1000),
    ]);

    $product->categories()->attach($category);

    return $product;
}
