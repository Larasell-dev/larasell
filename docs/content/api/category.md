---
title: Category API
description: Fetch sibling categories, child categories, descendants, and products from a category.
---

# Category API

The category model exposes relationships and query helpers for building
storefront navigation and product listing pages.

## Getting the root category

Use `root()` when you want the visible top-level category.

```php
use Larasell\Larasell\Models\Category;

$root = Category::query()->root()->first();
```

## Getting the sibling categories

Use `siblings()` when you want the visible categories that share the
same parent as the current category.

```php
use Larasell\Larasell\Models\Category;

$siblings = $category->siblings()->get();
```

The current category is excluded from the result.

## Getting the child categories

Use `children()` when you only need the direct child categories.

```php
use Larasell\Larasell\Models\Category;

$children = $category->children()->get();
```

Use `descendants()` when you need the visible category tree below the
current category.

```php
use Larasell\Larasell\Models\Category;

$descendants = $category->descendants()->get();
```

The `descendants` relationship recursively eager loads nested children.

## Getting products of a category

Use `products()` to query products attached to the category.

```php
use Larasell\Larasell\Models\Category;

$products = $category->products()->get();
```

For storefront pages, filter the products with the `visible()` scope so
inactive products are not shown.

```php
use Larasell\Larasell\Models\Category;

$products = $category->products()->visible()->get();
```
