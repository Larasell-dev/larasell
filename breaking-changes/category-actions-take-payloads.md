# Category actions take payloads instead of attribute arrays

`CreateCategory`, `UpdateCategory` and `MoveCategory` now take typed payloads, so callers can't
pass unknown or misspelled attributes. The payloads can carry `Extensions`, which the actions hand
to `CategoryCreated` and `CategoryUpdated` for an app's own listeners. `MoveCategory` now also
numbers the new siblings from 0 and puts the category at the given position.

## Who is affected

Apps that call `CreateCategory`, `UpdateCategory`, `MoveCategory`, `CreateCategoryWithUrls` or
`UpdateCategoryWithUrls` directly. Apps that rely on `CategoryUpdated` only firing when an
attribute changed: it now also fires, with `$changed === []`, when extensions were passed.

## How to upgrade

```php
// Before
app(CreateCategory::class)->handle(['name' => ['en' => 'Shoes'], 'parent_id' => $parent->id, 'status' => Visibility::Visible]);
app(UpdateCategory::class)->handle(CategoryId::of($shoes), ['name' => ['en' => 'Footwear']]);
app(MoveCategory::class)->handle(CategoryId::of($boots), CategoryId::of($sale), 2);

// After
use Larasell\Larasell\Catalog\Actions\CreateCategoryPayload;
use Larasell\Larasell\Catalog\Actions\MoveCategoryPayload;
use Larasell\Larasell\Catalog\Actions\UpdateCategoryPayload;
use Larasell\Larasell\Translatable;

app(CreateCategory::class)->handle(new CreateCategoryPayload(
    name: new Translatable(['en' => 'Shoes']),
    status: Visibility::Visible,
    parentId: CategoryId::of($parent),
));
app(UpdateCategory::class)->handle(CategoryId::of($shoes), new UpdateCategoryPayload(name: new Translatable(['en' => 'Footwear'])));
app(MoveCategory::class)->handle(CategoryId::of($boots), new MoveCategoryPayload(CategoryId::of($sale), 2));
```

`CreateCategoryWithUrls::handle()` and `UpdateCategoryWithUrls::handle()` take the same payloads in
place of their `$attributes` array.
