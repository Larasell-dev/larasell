# Actions, events, jobs and sales contexts use typed IDs

The catalog, sales channel and storefront actions, the workflows, and the jobs
that release expired orders now take typed IDs such as `ProductId` or
`CategoryId` instead of bare numbers. Their events and `SalesContext`
carry typed IDs too. A number meant for one kind of record can no longer be
passed where another is expected, e.g. a variant ID to `DeleteProduct`.

Each ID class wraps a positive `int` (`->value`) and is named after its record:

| Module | IDs |
| --- | --- |
| Catalog (`Larasell\Larasell\Catalog`) | `ProductId`, `VariantId`, `CategoryId`, `AttributeId`, `AttributeValueId`, `ImageId` |
| Sales channels (`Larasell\Larasell\SalesChannels`) | `SalesChannelId` |
| Storefront (`Larasell\Larasell\Storefront`) | `EntryPointId` |
| Carts (`Larasell\Larasell\Carts`) | `CartId`, `CartItemId` |
| Checkout (`Larasell\Larasell\Checkout`) | `OrderId` |

Models keep their `int` keys; nothing changes inside a module.

## Who is affected

Apps that call these actions or workflows, listen to their events, construct
`SalesContext` or `EntryPoint` themselves, or pass a channel ID to
`SalesContext::for()`.

## How to upgrade

Wrap IDs where you call an action, and read `->value` where you need the number:

```php
use Larasell\Larasell\Catalog\Actions\SetProductCategories;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\ProductId;

// Before
app(UpdateProduct::class)->handle($product->id, ['sku' => 'B-2']);
app(SetProductCategories::class)->handle($product->id, [$shoes->id, $sale->id]);

// After
app(UpdateProduct::class)->handle(ProductId::of($product), ['sku' => 'B-2']);
app(SetProductCategories::class)->handle(ProductId::of($product), CategoryId::many([$shoes->id, $sale->id]));

// From request input; throws Larasell\Larasell\Ids\InvalidId for anything but a positive whole number
app(UpdateProduct::class)->handle(ProductId::fromString($request->route('product')), $attributes);
```

In listeners, compare IDs with `equals()` or their `->value`, never with `===`,
which compares instances:

```php
// Before
if ($event->productId === $product->id) { /* ... */ }

// After
if ($event->productId->value === $product->id) { /* ... */ }
if ($event->productId->equals(ProductId::of($product))) { /* ... */ }
```

`CreateProductVariant` now returns a `VariantId` and `GenerateProductVariants`
a `list<VariantId>`. `SalesContext::for()` and `StorefrontContext::for()` take
a channel key or a `SalesChannelId`, no longer an `int`.
`ReleaseExpiredInventoryForOrder` and `ReleaseExpiredPromotionRedemptionsForOrder`
take an `OrderId`. In `CartMerged`, `$sourceCartId` is a `CartId`; in
`CartMergeLineAdjustment`, `$variantId` is a `VariantId`.
