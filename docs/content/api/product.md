---
title: Product API
description: Fetch visible products and their attached categories.
---

# Product API

The product model exposes relationships and query helpers for building
storefront product listing and detail pages.

Products include a nullable, translatable `description` field for longer
plain-text product copy. Like `name`, it is cast to `Translatable` when set.

Products must also include a currency-independent `price` field. Price values
are integer strings in minor units and are cast to `Larasell\Larasell\Price`
on the model. Supply the cart or order currency when formatting a price.

Products include a nullable `stock` field that defaults to `null`.
When `stock` is `null`, Larasell does not track inventory for the
product and customers may buy any quantity. By default, products also
allow backorders, which means products with a finite stock can be
purchased even when stock would go below zero. Set `allow_backorders` to
`false` when a product should stop selling once stock reaches zero.
Products can also define nullable `min_quantity` and `max_quantity`
fields. Both default to `null`. When set, each value must be at least
`1`, and `min_quantity` cannot exceed `max_quantity`.

```php
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

$product = Product::create([
    'slug' => 'basic-plan',
    'name' => 'Basic Plan',
    'price' => Price::of(1299),
]);

$amount = $product->price->amount();
$formatted = Price::format($product->price, 'USD');
```

## Managing stock

Use `stock` to store the current inventory count for the product. Leave
`stock` as `null` for products that do not have inventory limits.
Products allow backorders by default through `allow_backorders`.

```php
$product->stock; // null
$product->allow_backorders; // true

$product->update([
    'stock' => 10,
    'min_quantity' => 1,
    'max_quantity' => 20,
    'allow_backorders' => false,
]);
```

## Getting visible products

Use `visible()` when you want to query only products that should be
shown on storefront pages.

```php
use Larasell\Larasell\Models\Product;

$products = Product::query()->visible()->get();
```

The scope filters products where `status` is `Visibility::Visible`.

## Getting categories of a product

Use `categories()` to query the categories attached to the product.

```php
use Larasell\Larasell\Models\Product;

$categories = $product->categories()->get();
```

You can eager load categories when fetching products for a listing page.

```php
use Larasell\Larasell\Models\Product;

$products = Product::query()
    ->visible()
    ->with('categories')
    ->get();
```

## Filtering product listings

`ProductListingRequest::products()` applies the current category, sort,
and product option filters from the request query string.

```php
$products = $request->products()->get();
```

Filter by product option slug and option value slug with the `options`
query parameter.

```text
/c/shirts?options[size][]=small&options[size][]=medium&options[color]=black
```

Multiple values for the same option match any selected value. Multiple
options must all match.

## Managing product images

Product images are stored as reusable image records and attached to
products through a pivot table. The pivot table stores the product
specific `position`, so the same image can be used by multiple products
with a different order for each product.

Larasell stores product image paths in the database and resolves URLs
through Laravel's filesystem. Configure the disk in
`config/larasell.php` or with environment variables:

```env
LARASELL_IMAGES_DISK=public
LARASELL_IMAGES_PATH=larasell/products
LARASELL_IMAGES_VISIBILITY=public
```

The disk may be any Laravel filesystem disk, including local, S3, or a
custom disk registered by the application.

Create an image record with the stored file path, then attach it to the
product with a position.

```php
use Larasell\Larasell\Models\ProductImage;

$image = ProductImage::create([
    'path' => 'products/basic-plan/front.jpg',
    'alt' => 'Basic Plan product image',
]);

$product->images()->attach($image, [
    'position' => 0,
]);
```

The `images()` relationship includes the pivot position and sorts images
by that position.

```php
$product = Product::query()
    ->with('images')
    ->where('slug', 'basic-plan')
    ->firstOrFail();

foreach ($product->images as $image) {
    $url = $image->url();
    $position = $image->pivot->position;
}
```

You may update a product's image order by updating the pivot data.

```php
$product->images()->updateExistingPivot($image->id, [
    'position' => 1,
]);
```

To replace all image associations and their positions, use `sync` with
pivot values.

```php
$product->images()->sync([
    $firstImage->id => ['position' => 0],
    $secondImage->id => ['position' => 1],
]);
```

## Managing product options

Product options are reusable typed definitions, such as `Size`, `Color`,
or `Gift wrap`. Supported option types are `text`, `number`, and
`boolean`. Each option owns its available values, and products are
assigned the specific values they support.

```php
use Larasell\Larasell\Enums\ProductOptionType;
use Larasell\Larasell\Models\ProductOption;

$size = ProductOption::create([
    'slug' => 'size',
    'name' => 'Size',
    'type' => ProductOptionType::Text,
]);

$small = $size->values()->create([
    'slug' => 'small',
    'name' => 'Small',
    'value' => 'small',
    'position' => 0,
]);

$product->optionValues()->attach($small);
```

Option values must match their parent option type. Text options accept
strings, number options accept integers or floats, and boolean options
accept booleans.

```php
$giftWrap = ProductOption::create([
    'slug' => 'gift-wrap',
    'name' => 'Gift wrap',
    'type' => ProductOptionType::Boolean,
]);

$giftWrap->values()->create([
    'slug' => 'yes',
    'name' => 'Yes',
    'value' => true,
]);
```

Use `withOptions()` to load product option values with their parent
option when rendering a product page.

```php
$product = Product::query()
    ->withOptions()
    ->where('slug', 'basic-plan')
    ->firstOrFail();

foreach ($product->optionValues as $value) {
    $optionName = $value->option->name;
    $valueName = $value->name;
}
```

## Getting a visible product by slug

For product detail pages, combine the slug with the `visible()` scope so
inactive products are not shown.

```php
use Larasell\Larasell\Models\Product;

$product = Product::query()
    ->visible()
    ->where('slug', $slug)
    ->firstOrFail();
```

Resolve the product description for the current locale with `get()`.

```php
$description = $product->description?->get();
```
