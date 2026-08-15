---
title: Product API
description: Fetch visible products and their attached categories.
---

# Product API

The product model exposes relationships and query helpers for building
storefront product listing and detail pages.

Products include a nullable `description` text field for longer
plain-text product copy.

Products must also include a `price` field. Price values use minor
units, such as cents for USD, and are cast to
`Larasell\Larasell\Price` on the model.

```php
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Price;

$product = Product::create([
    'slug' => 'basic-plan',
    'name' => 'Basic Plan',
    'price' => Price::of(1299, Currency::USD),
]);

$amount = $product->price->amount();
$currency = $product->price->currency();
$currencyCode = $product->price->currencyCode();
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

You can render the product description directly from the model.

```php
$description = $product->description;
```
