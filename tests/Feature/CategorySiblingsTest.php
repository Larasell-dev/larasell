<?php

use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Category;

it('exposes siblings as a relation property', function () {
    $clothing = Category::query()->create([
        'slug' => ['en' => 'clothing'],
        'name' => ['en' => 'Clothing'],
    ]);

    $shirts = Category::query()->create([
        'slug' => ['en' => 'shirts'],
        'name' => ['en' => 'Shirts'],
        'parent_id' => $clothing->id,
    ]);

    $shoes = Category::query()->create([
        'slug' => ['en' => 'shoes'],
        'name' => ['en' => 'Shoes'],
        'parent_id' => $clothing->id,
    ]);

    Category::query()->create([
        'slug' => ['en' => 'hidden-scarves'],
        'name' => ['en' => 'Scarves'],
        'parent_id' => $clothing->id,
        'status' => Visibility::Hidden,
    ]);

    Category::query()->create([
        'slug' => ['en' => 'electronics'],
        'name' => ['en' => 'Electronics'],
    ]);

    $siblings = $shirts->siblings;

    expect($siblings)->toHaveCount(1)
        ->and($siblings->first()->is($shoes))->toBeTrue();
});

it('eager loads siblings for multiple categories', function () {
    $clothing = Category::query()->create([
        'slug' => ['en' => 'clothing'],
        'name' => ['en' => 'Clothing'],
    ]);

    $shirts = Category::query()->create([
        'slug' => ['en' => 'shirts'],
        'name' => ['en' => 'Shirts'],
        'parent_id' => $clothing->id,
    ]);

    $shoes = Category::query()->create([
        'slug' => ['en' => 'shoes'],
        'name' => ['en' => 'Shoes'],
        'parent_id' => $clothing->id,
    ]);

    $categories = Category::query()->whereNotNull('parent_id')->with('siblings')->get();

    expect($categories)->toHaveCount(2);

    $categories->each(function (Category $category) use ($shirts, $shoes): void {
        $siblings = $category->siblings;

        expect($siblings)->toHaveCount(1);

        if ($category->is($shirts)) {
            expect($siblings->first()->is($shoes))->toBeTrue();
        } else {
            expect($siblings->first()->is($shirts))->toBeTrue();
        }
    });
});
