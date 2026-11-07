<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateCategoryPayload;
use Larasell\Larasell\Catalog\Actions\DeleteCategory;
use Larasell\Larasell\Catalog\Actions\DeleteCategoryTree;
use Larasell\Larasell\Catalog\Actions\MoveCategory;
use Larasell\Larasell\Catalog\Actions\MoveCategoryPayload;
use Larasell\Larasell\Catalog\Actions\UpdateCategory;
use Larasell\Larasell\Catalog\Actions\UpdateCategoryPayload;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\CategoryCreated;
use Larasell\Larasell\Catalog\Events\CategoryDeleted;
use Larasell\Larasell\Catalog\Events\CategoryMoved;
use Larasell\Larasell\Catalog\Events\CategoryUpdated;
use Larasell\Larasell\Catalog\Exceptions\CategoryCycle;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Extensions;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

/** What an app passes along with a catalog action for its own listeners, e.g. an admin's form section. */
final readonly class CategoryNote
{
    public function __construct(public string $text) {}
}

function createCategory(string $name, ?Category $parent = null): Category
{
    return app(CreateCategory::class)->handle(new CreateCategoryPayload(
        name: new Translatable(['en' => $name]),
        parentId: $parent === null ? null : CategoryId::of($parent),
    ));
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

    app(UpdateCategory::class)->handle(CategoryId::of($shoes), new UpdateCategoryPayload(name: new Translatable(['en' => 'Footwear'])));

    $shoes = Category::query()->findOrFail($shoes->id);
    expect($shoes->name->get('en'))->toBe('Footwear')
        ->and($shoes->status)->toBe(Visibility::Visible)
        ->and($shoes->parent_id)->toBeNull();
    Event::assertDispatched(CategoryUpdated::class, fn (CategoryUpdated $event): bool => $event->changed === ['name']);
});

it('hands what the app passed along to the category\'s events, even when nothing else changed', function () {
    Event::fake([CategoryCreated::class, CategoryUpdated::class]);

    $shoes = app(CreateCategory::class)->handle(new CreateCategoryPayload(
        name: new Translatable(['en' => 'Shoes']),
        extensions: new Extensions(new CategoryNote('new')),
    ));
    app(UpdateCategory::class)->handle(CategoryId::of($shoes), new UpdateCategoryPayload(extensions: new Extensions(new CategoryNote('edited'))));

    Event::assertDispatched(CategoryCreated::class, fn (CategoryCreated $event): bool => $event->extensions->get(CategoryNote::class)?->text === 'new');
    Event::assertDispatched(CategoryUpdated::class, fn (CategoryUpdated $event): bool => $event->changed === []
        && $event->extensions->get(CategoryNote::class)?->text === 'edited');
});

it('announces no update when nothing changed and nothing was passed along', function () {
    $shoes = createCategory('Shoes');
    Event::fake([CategoryUpdated::class]);

    app(UpdateCategory::class)->handle(CategoryId::of($shoes), new UpdateCategoryPayload(name: new Translatable(['en' => 'Shoes'])));

    Event::assertNotDispatched(CategoryUpdated::class);
});

it('rolls the category back when a listener of its event fails', function () {
    $shoes = createCategory('Shoes');
    Event::listen(CategoryUpdated::class, fn () => throw new RuntimeException('The note is invalid.'));

    expect(fn () => app(UpdateCategory::class)->handle(CategoryId::of($shoes), new UpdateCategoryPayload(name: new Translatable(['en' => 'Footwear']))))
        ->toThrow(RuntimeException::class)
        ->and(Category::query()->findOrFail($shoes->id)->name->get('en'))->toBe('Shoes');
});

it('moves a category below another one and announces both parents', function () {
    $shoes = createCategory('Shoes');
    $sale = createCategory('Sale');
    createCategory('Old stock', $sale);
    $boots = createCategory('Boots', $shoes);
    Event::fake([CategoryMoved::class]);

    app(MoveCategory::class)->handle(CategoryId::of($boots), new MoveCategoryPayload(CategoryId::of($sale)));

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

    expect(fn () => app(MoveCategory::class)->handle(CategoryId::of($shoes), new MoveCategoryPayload(CategoryId::of($targets[$target]))))->toThrow(CategoryCycle::class)
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

it('moves a category to a position among its new siblings and numbers them again from 0', function (?int $position, array $order) {
    $sale = createCategory('Sale');
    $old = createCategory('Old stock', $sale);
    $outlet = createCategory('Outlet', $sale);
    $shoes = createCategory('Shoes');
    $boots = createCategory('Boots', $shoes);
    // A gap, as a deleted sibling leaves it.
    $outlet->update(['position' => 5]);

    app(MoveCategory::class)->handle(CategoryId::of($boots), new MoveCategoryPayload(CategoryId::of($sale), $position));

    expect(Category::query()->where('parent_id', $sale->id)->orderBy('position')->get()->map(fn (Category $category): array => [$category->name->get('en'), $category->position])->all())
        ->toBe($order);
})->with([
    'first' => [0, [['Boots', 0], ['Old stock', 1], ['Outlet', 2]]],
    'between' => [1, [['Old stock', 0], ['Boots', 1], ['Outlet', 2]]],
    'past the last' => [9, [['Old stock', 0], ['Outlet', 1], ['Boots', 2]]],
    'without a position' => [null, [['Old stock', 0], ['Outlet', 1], ['Boots', 2]]],
]);

it('reorders a category among its own siblings', function () {
    $shoes = createCategory('Shoes');
    $boots = createCategory('Boots', $shoes);
    createCategory('Sandals', $shoes);
    createCategory('Sneakers', $shoes);

    app(MoveCategory::class)->handle(CategoryId::of($boots), new MoveCategoryPayload(CategoryId::of($shoes), 2));

    expect(Category::query()->where('parent_id', $shoes->id)->orderBy('position')->get()->map(fn (Category $category): string => $category->name->get('en'))->all())
        ->toBe(['Sandals', 'Sneakers', 'Boots']);
});

it('deletes a category with every category below it, deepest first, and keeps their products', function () {
    $shoes = createCategory('Shoes');
    $boots = createCategory('Boots', $shoes);
    $winter = createCategory('Winter boots', $boots);
    $sale = createCategory('Sale');
    $product = Product::create(['name' => 'Snow Boots', 'price' => Price::of(1000)]);
    $product->categories()->attach($winter->id);
    Event::fake([CategoryDeleted::class]);

    app(DeleteCategoryTree::class)->handle(CategoryId::of($shoes));

    expect(Category::query()->pluck('id')->all())->toBe([$sale->id])
        ->and(Product::query()->whereKey($product->id)->exists())->toBeTrue();
    $deleted = [];
    Event::assertDispatched(CategoryDeleted::class, function (CategoryDeleted $event) use (&$deleted): bool {
        $deleted[] = $event->categoryId->value;

        return true;
    });
    expect($deleted)->toBe([$winter->id, $boots->id, $shoes->id]);
});
