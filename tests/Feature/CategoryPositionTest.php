<?php

use Larasell\Larasell\Models\Category;

function categoryWithPosition(string $slug, ?Category $parent = null, ?int $position = null): Category
{
    return Category::query()->create(array_filter([
        'slug' => ['en' => $slug],
        'name' => ['en' => ucfirst($slug)],
        'parent_id' => $parent?->id,
        'position' => $position,
    ], fn (mixed $value): bool => $value !== null));
}

it('puts a new category after its siblings', function () {
    $clothing = categoryWithPosition('clothing');
    categoryWithPosition('shirts', $clothing, position: 4);
    $shoes = categoryWithPosition('shoes', $clothing);
    $electronics = categoryWithPosition('electronics');

    expect($clothing->position)->toBe(0)
        ->and($shoes->position)->toBe(5)
        ->and($electronics->position)->toBe(1);
});

it('orders categories by their position', function () {
    $clothing = categoryWithPosition('clothing', position: 2);
    $electronics = categoryWithPosition('electronics', position: 0);
    $home = categoryWithPosition('home', position: 1);

    $shoes = categoryWithPosition('shoes', $clothing, position: 1);
    $shirts = categoryWithPosition('shirts', $clothing, position: 2);
    $hats = categoryWithPosition('hats', $clothing, position: 0);

    $sneakers = categoryWithPosition('sneakers', $shoes, position: 1);
    $boots = categoryWithPosition('boots', $shoes, position: 0);

    $ids = fn (iterable $categories): array => collect($categories)->pluck('id')->all();

    expect($ids(Category::root()->get()))->toBe([$electronics->id, $home->id, $clothing->id])
        ->and($ids($clothing->children))->toBe([$hats->id, $shoes->id, $shirts->id])
        ->and($ids($clothing->descendants))->toBe([$hats->id, $shoes->id, $shirts->id])
        ->and($ids($clothing->descendants[1]->descendants))->toBe([$boots->id, $sneakers->id])
        ->and($ids($shirts->siblings))->toBe([$hats->id, $shoes->id])
        ->and($ids(Category::query()->whereKey($shirts->id)->with('siblings')->first()->siblings))
        ->toBe([$hats->id, $shoes->id]);
});

it('orders categories that share a position by id', function () {
    $clothing = categoryWithPosition('clothing', position: 0);
    $electronics = categoryWithPosition('electronics', position: 0);

    expect(Category::root()->pluck('id')->all())->toBe([$clothing->id, $electronics->id]);
});
