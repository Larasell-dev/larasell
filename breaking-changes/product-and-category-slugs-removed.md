# Product and category slugs are replaced by SEO URLs

The `slug` columns of `larasell_products` and `larasell_categories` are
dropped, together with the routing built on them. Storefront URLs now live in
the new SEO module (`larasell_seo_urls`): each product and category gets a URL
per channel and locale, renames keep the old URL as a redirect, and deleted
pages answer 410 or redirect to a replacement.

**Existing slugs are not carried over.** After migrating, products live at
`p/{name}` and categories at `{parent path}/{name}`, built from their names.
Links to old slug URLs answer 404 unless you redirect them yourself.

## Who is affected

Apps that use any of these:

- the `slug` attribute of `Product` or `Category`, including `'slug' => …` in
  `create()` or `update()` calls and queries such as `where('slug->en', …)`;
- `Larasell\Larasell\Routing\ProductDetailRoute`,
  `Larasell\Larasell\Routing\ProductListingRoute`, or
  `Larasell\Larasell\Routing\CategoryUrl`;
- `Larasell\Larasell\Http\Requests\ProductDetailRequest`;
- `ProductListingRequest` with a `{category}` slug path;
- `OrderItem::$product_slug`. Checkout no longer writes it; the column stays,
  so orders placed before the upgrade keep their value.

Attribute and attribute value slugs (`ProductAttribute::$slug`,
`ProductAttributeValue::$slug`) are unchanged.

## How to upgrade

Run the migrations, then create URLs for your existing products and
categories:

```bash
php artisan migrate
php artisan larasell:regenerate-seo-urls
```

Stop passing `slug` when creating products and categories:

```php
// Before
Product::create(['slug' => 'boots', 'name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);

// After
Product::create(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);
```

Register storefront routes inside `Route::storefront()`. Typed parameters are
resolved from SEO URLs, and `Route::seo()` serves categories at any depth and
custom URLs:

```php
// Before
use Larasell\Larasell\Routing\ProductDetailRoute;
use Larasell\Larasell\Routing\ProductListingRoute;

ProductDetailRoute::get(ShowProduct::class, prefix: 'p');
ProductListingRoute::get(ShowCategory::class, prefix: 'c');

// After
use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;

Route::storefront(function () {
    Route::get('p/{product}', ShowProduct::class);
    Route::seo([Category::class => ShowCategory::class]);
});
```

Controllers that used `ProductDetailRequest` take the product directly.
`ProductListingRequest` keeps working and reads the category the route bound:

```php
// Before
public function __invoke(ProductDetailRequest $request)
{
    $product = $request->product();
}

// After
public function __invoke(Product $product) {}
```

Generate links with the `Seo` facade instead of `CategoryUrl` or slugs:

```php
// Before
CategoryUrl::make($category);
url('p/'.$product->slug->get());

// After
use Larasell\Larasell\Seo\Facades\Seo;

Seo::url($category);
Seo::url($product);
```

Deleted and hidden pages throw `SeoPageGone` (410) and `SeoPageHidden` (404).
Render them like any exception, e.g. in `bootstrap/app.php`:

```php
use Larasell\Larasell\Seo\Exceptions\SeoPageGone;

$exceptions->render(fn (SeoPageGone $e) => response()->view('discontinued', $e->snapshot, 410));
```
