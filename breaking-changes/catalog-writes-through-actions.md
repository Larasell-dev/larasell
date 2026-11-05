# Write the catalog through its actions

Products, variants, categories, attributes, attribute values and images are
now written through actions in `Larasell\Larasell\Catalog\Actions`, such as
`CreateProduct`, `UpdateProduct`, `MoveCategory` and `DeleteProduct`. Each
action runs in its own transaction, locks the rows it changes, checks its
rules before writing, and dispatches a domain event from
`Larasell\Larasell\Catalog\Events` (`ProductUpdated`, `CategoryMoved`, …).
Other modules, such as SEO, react to those events inside the same
transaction, so a product and its URL are saved together or not at all.

**Writing a model directly no longer updates anything else.** `$product->update()`,
`Product::create()` and `$category->delete()` still write the row, but no
event is dispatched: SEO URLs are not created, moved, or marked as gone, and
the rules other modules set (no delete of a product with open orders or
reserved stock) are not checked.

## Who is affected

Apps, imports and seeders that create, change or delete catalog models with
Eloquent. Reading models is unchanged.

## How to upgrade

Call the action that matches the change. Actions take IDs and plain input:

```php
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Actions\DeleteProduct;
use Larasell\Larasell\Catalog\Actions\MoveCategory;
use Larasell\Larasell\Catalog\Actions\SetProductCategories;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;

// Before
$product = Product::create(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);
$product->categories()->attach($shoes);
$product->update(['name' => ['en' => 'Leather Boots']]);
$boots->update(['parent_id' => $sale->id]);
$product->delete();

// After
$product = app(CreateProduct::class)->handle(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);
app(SetProductCategories::class)->handle($product->id, [$shoes->id]);
app(UpdateProduct::class)->handle($product->id, ['name' => ['en' => 'Leather Boots']]);
app(MoveCategory::class)->handle($boots->id, $sale->id);
app(DeleteProduct::class)->handle($product->id);
```

`UpdateCategory` no longer accepts `parent_id` or `position`; use
`MoveCategory`, which refuses to put a category below itself or one of its
descendants (`CategoryCycle`). `DeleteProduct` throws `ProductInUse` while
an open order contains the product or units of it are reserved.

After bulk writes that bypassed the actions, bring the URLs up to date:

```bash
php artisan larasell:regenerate-seo-urls
```
