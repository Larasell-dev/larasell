# Catalog models live in `Larasell\Larasell\Catalog\Models`

The catalog is now a module of its own, so its models moved out of the shared
`Larasell\Larasell\Models` namespace:

| Before | After |
| --- | --- |
| `Larasell\Larasell\Models\Product` | `Larasell\Larasell\Catalog\Models\Product` |
| `Larasell\Larasell\Models\ProductVariant` | `Larasell\Larasell\Catalog\Models\ProductVariant` |
| `Larasell\Larasell\Models\Category` | `Larasell\Larasell\Catalog\Models\Category` |
| `Larasell\Larasell\Models\ProductAttribute` | `Larasell\Larasell\Catalog\Models\ProductAttribute` |
| `Larasell\Larasell\Models\ProductAttributeValue` | `Larasell\Larasell\Catalog\Models\ProductAttributeValue` |
| `Larasell\Larasell\Models\ProductImage` | `Larasell\Larasell\Catalog\Models\ProductImage` |
| `Larasell\Larasell\Models\Relations\Siblings` | `Larasell\Larasell\Catalog\Models\Relations\Siblings` |

Tables and columns are unchanged.

## Who is affected

Apps that import any of the classes above. Polymorphic relations that stored
one of these class names in a `*_type` column also need their rows updated,
unless the app uses a morph map.

## How to upgrade

Update the imports:

```php
// Before
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Product;

// After
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
```

This replaces them across an app on Linux (on macOS, use `sed -i ''`):

```bash
grep -rlE 'Larasell\\Larasell\\Models\\(Product|Category|Relations)' app config database routes tests \
  | xargs sed -i -E 's/Larasell\\Larasell\\Models\\(Product|Category|Relations)/Larasell\\Larasell\\Catalog\\Models\\\1/g'
```
