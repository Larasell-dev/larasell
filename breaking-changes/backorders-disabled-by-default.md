# Products no longer allow backorders by default

`larasell_products.allow_backorders` now defaults to `false` instead of `true`,
so a product created without `allow_backorders` can't be sold beyond its stock.
Allowing overselling should be an explicit choice, not an accident.

## Who is affected

Apps that create products without setting `allow_backorders` and rely on them
accepting orders when stock runs out. Existing products keep their stored
value; only products created after running the migration are affected. Apps
that always set `allow_backorders` explicitly are not affected.

## How to upgrade

Run the new migration:

```bash
php artisan migrate
```

Then pass `allow_backorders` wherever products should still accept backorders:

```php
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

// Before
Product::create([
    'slug' => 'gift-card',
    'name' => 'Gift card',
    'price' => Price::of(2500),
]);

// After
Product::create([
    'slug' => 'gift-card',
    'name' => 'Gift card',
    'price' => Price::of(2500),
    'allow_backorders' => true,
]);
```
