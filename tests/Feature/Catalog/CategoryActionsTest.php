<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\DeleteCategory;
use Larasell\Larasell\Catalog\Actions\MoveCategory;
use Larasell\Larasell\Catalog\Actions\UpdateCategory;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\CategoryCreated;
use Larasell\Larasell\Catalog\Events\CategoryDeleted;
use Larasell\Larasell\Catalog\Events\CategoryMoved;
use Larasell\Larasell\Catalog\Events\CategoryUpdated;
use Larasell\Larasell\Catalog\Exceptions\CategoryCycle;
use Larasell\Larasell\Catalog\Models\Category;

function createCategory(string $name, ?Category $parent = null): Category
{
    return app(CreateCategory::class)->handle(['name' => ['en' => $name], 'parent_id' => $parent?->id]);
}

it('creates a category after its siblings and announces it', function () {
    Event::fake([CategoryCreated::class]);
    $shoes = createCategory('Shoes');
    $boots = createCategory('Boots', $shoes);
    $sandals = createCategory('Sandals', $shoes);

    expect([$boots->position, $sandals->position])->toBe([0, 1]);
    Event::assertDispatched(CategoryCreated::class, fn (CategoryCreated $event): bool => $event->categoryId->value === $sandals->id
        && $event->parentId?->value === $shoes->id);
});

it('updates a category but leaves moving it to MoveCategory', function () {
    $shoes = createCategory('Shoes');
    $sale = createCategory('Sale');
    Event::fake([CategoryUpdated::class]);

    app(UpdateCategory::class)->handle(CategoryId::of($shoes), ['name' => ['en' => 'Footwear']]);

    expect(Category::query()->findOrFail($shoes->id)->name->get('en'))->toBe('Footwear');
    Event::assertDispatched(CategoryUpdated::class, fn (CategoryUpdated $event): bool => $event->changed === ['name']);

    app(UpdateCategory::class)->handle(CategoryId::of($shoes), ['parent_id' => $sale->id]);
})->throws(InvalidArgumentException::class, 'MoveCategory');

it('moves a category below another one and announces both parents', function () {
    $shoes = createCategory('Shoes');
    $sale = createCategory('Sale');
    createCategory('Old stock', $sale);
    $boots = createCategory('Boots', $shoes);
    Event::fake([CategoryMoved::class]);

    app(MoveCategory::class)->handle(CategoryId::of($boots), CategoryId::of($sale));

    $boots = Category::query()->findOrFail($boots->id);
    expect($boots->parent_id)->toBe($sale->id)
        ->and($boots->position)->toBe(1);
    Event::assertDispatched(CategoryMoved::class, fn (CategoryMoved $event): bool => $event->categoryId->equals(CategoryId::of($boots))
        && $event->oldParentId?->equals(CategoryId::of($shoes)) && $event->newParentId?->equals(CategoryId::of($sale)));
});

it('refuses to move a category below itself or one of its descendants', function (string $target) {
    $shoes = createCategory('Shoes');
    $boots = createCategory('Boots', $shoes);
    $winter = createCategory('Winter boots', $boots);
    $targets = ['itself' => $shoes, 'its child' => $boots, 'its grandchild' => $winter];

    expect(fn () => app(MoveCategory::class)->handle(CategoryId::of($shoes), CategoryId::of($targets[$target])))->toThrow(CategoryCycle::class)
        ->and(Category::query()->findOrFail($shoes->id)->parent_id)->toBeNull();
})->with(['itself', 'its child', 'its grandchild']);

it('deletes a category, moves its children to the top, and announces them', function () {
    $shoes = createCategory('Shoes');
    $boots = createCategory('Boots', $shoes);
    Event::fake([CategoryDeleted::class]);

    app(DeleteCategory::class)->handle(CategoryId::of($shoes));

    expect(Category::query()->whereKey($shoes->id)->exists())->toBeFalse()
        ->and(Category::query()->findOrFail($boots->id)->parent_id)->toBeNull();
    Event::assertDispatched(CategoryDeleted::class, fn (CategoryDeleted $event): bool => $event->categoryId->value === $shoes->id
        && $event->parentId === null && $event->name->get('en') === 'Shoes' && CategoryId::values($event->childIds) === [$boots->id]);
});
