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

Products may also include a nullable `compare_at` field with the same shape.
It is a display-only previous price in the same tax mode as `price`. Carts,
taxes, and promotions ignore it. A product is on sale when `compare_at` is
higher than `price`. Values that are missing, equal, or lower are stored but
are not treated as a sale.

```php
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

$product = Product::create([
    'slug' => 'basic-plan',
    'name' => 'Basic Plan',
    'sku' => 'PLAN-BASIC',
    'barcode' => null,
    'price' => Price::of(1299),
    'compare_at' => Price::of(1999),
]);

$amount = $product->price->amount();
$formatted = Price::format($product->price, 'USD');
$product->onSale(); // true
```

Products may have a nullable `sku` and `barcode`. Both identifiers are stored
as strings, preserve leading zeroes, and must be unique when present. Use `sku`
for the merchant's internal stock identifier and `barcode` for an external
identifier such as an EAN, UPC, or GTIN.

Products include a nullable `stock` field that defaults to `null`.
When `stock` is `null`, Larasell does not track inventory for the
product and customers may buy any quantity. By default, products also
allow backorders, which means products with a finite stock can be
purchased even when stock would go below zero. Set `allow_backorders` to
`false` when a product should stop selling once stock reaches zero.
Products can also define nullable `min_quantity` and `max_quantity`
fields. Both default to `null`. When set, each value must be at least
`1`, and `min_quantity` cannot exceed `max_quantity`.

Products may include a nullable `weight` and nullable `dimensions` value.
Weight stores an amount and unit (`g`, `kg`, `oz`, `lb`). Dimensions store
length, width, height, and a shared length unit (`mm`, `cm`, `m`, `in`,
`ft`). Amounts are non-negative decimal strings. Comparisons convert to a
canonical unit, so `1 kg` equals `1000 g` and `1 in` equals `25.4 mm`.

```php
use Larasell\Larasell\Dimensions;
use Larasell\Larasell\Enums\LengthUnit;
use Larasell\Larasell\Enums\WeightUnit;
use Larasell\Larasell\Length;
use Larasell\Larasell\Weight;

$product->update([
    'weight' => Weight::of(450, WeightUnit::Gram),
    'dimensions' => Dimensions::of(30, 20, 2, LengthUnit::Centimeter),
]);

$product->weight->greaterThan(Weight::of('0.4', WeightUnit::Kilogram));
$product->dimensions->longestSide()->lessThan(Length::of(1, LengthUnit::Meter));
$product->dimensions->fitsInside(Dimensions::of(40, 30, 10, LengthUnit::Centimeter));
```

Leave either field `null` when the measurement is unknown. Variants inherit
the product values until they set their own.

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
and product attribute filters from the request query string.

```php
$products = $request->products()->get();
```

Filter by product attribute slug and attribute value slug with the `attributes`
query parameter.

```text
/c/shirts?attributes[size][]=small&attributes[size][]=medium&attributes[color]=black
```

Multiple values for the same attribute match any selected value. Multiple
attributes must all match.

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
custom disk registered by the application. Raster files and SVG are
accepted. `$image->isSvg()` is true for vector originals; placeholders
are always `null` for those files.

When a raster file exists on the configured disk, Larasell can generate
a placeholder at save time. Generation is off by default
(`NullPlaceholderGenerator`). SVG originals and missing files always
skip generation.

```php
$image->placeholder; // null unless a generator is configured
```

Enable a mechanism in `config/larasell.php` by setting a single
generator class.

```php
use Larasell\Larasell\Images\ColorPlaceholderGenerator;
use Larasell\Larasell\Images\LqipPlaceholderGenerator;
use Larasell\Larasell\Images\NullPlaceholderGenerator;

'images' => [
    'placeholder' => NullPlaceholderGenerator::class,
    // 'placeholder' => LqipPlaceholderGenerator::class,
    // 'placeholder' => ColorPlaceholderGenerator::class,
],
```

`LqipPlaceholderGenerator` stores a tiny JPEG data URI (`type: lqip`)
plus a hex `color`.

```php
$image->placeholder?->toArray();
// ['type' => 'lqip', 'value' => 'data:image/jpeg;base64,…', 'color' => '#c4a574']
```

Implement `PlaceholderGenerator` for ThumbHash, BlurHash, or a CDN URL.
Call `$image->refreshPlaceholder()` to regenerate after replacing the
stored file without changing `path`.

After you change `larasell.images.placeholder`, existing rows keep their
old payload until you rebuild them:

```bash
php artisan larasell:refresh-placeholders
```

The command walks every product image, including SVGs (which stay
`null`) and missing files (which become `null`). Use `--batch-size`
when the library is large:

```bash
php artisan larasell:refresh-placeholders --batch-size=250
```

## Displaying placeholders

The React `Image` component paints a placeholder only when you pass
one. With the default generator that value is `null`, so images render
like a normal `src`. After you enable a generator, pass
`$image->placeholder?->toArray()` to opt in:

```tsx
import Image from '../Components/Image'

<Image
  alt={image.alt ?? ''}
  className="size-full object-cover"
  placeholder={image.placeholder}
  src={image.url}
/>
```

`type: color` uses only the background. Encoded hashes such as
ThumbHash are ignored until you decode them into a `data:` URI (or
change `type` to `lqip`). SVG originals have a `null` placeholder.

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
by that position. `$product->thumbnail` and `$variant->thumbnail` are
the first image in that order (variants currently reuse the product
gallery).

```php
$product = Product::query()
    ->with('images')
    ->where('slug', 'basic-plan')
    ->firstOrFail();

$thumbnail = $product->thumbnail;
$variantThumbnail = $product->defaultVariant()->thumbnail;

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

## Managing product attributes

Product attributes are reusable typed definitions, such as `Size`, `Color`,
or `Gift wrap`. Supported attribute types are `text`, `number`, and
`boolean`. Each attribute owns its available values, and products are
assigned the specific values they support.

```php
use Larasell\Larasell\Enums\ProductAttributeType;
use Larasell\Larasell\Models\ProductAttribute;

$size = ProductAttribute::create([
    'slug' => 'size',
    'name' => 'Size',
    'type' => ProductAttributeType::Text,
]);

$small = $size->values()->create([
    'slug' => 'small',
    'name' => 'Small',
    'value' => 'small',
    'position' => 0,
]);

$product->attributeValues()->attach($small);
```

Choose which attached attributes define purchasable variants. Generation must
produce at least two combinations.

```php
$medium = $size->values()->create([
    'slug' => 'medium',
    'name' => 'Medium',
    'value' => 'medium',
]);
$product->attributeValues()->attach($medium);

$variants = $product->generateVariants([$size]);
```

The selected attributes are persisted in `variantDimensions()`. Calling the
generator again creates only missing combinations and preserves existing
variant data. Generated variants start hidden so the merchant can review SKU,
price, inventory, and availability before selling them.

Resolve a storefront selection using stable attribute and value slugs:

```php
$variant = $product->variantFor([
    'size' => 'small',
    'color' => 'black',
]);

$cart->add($variant, quantity: 2);
```

`ProductVariant` is the authoritative purchasable record. Its nullable price,
compare-at price, weight, dimensions, stock, backorder policy, and quantity
limits inherit from the product. SKU and barcode also inherit when omitted.
Use `compareAtPrice()` and `onSale()` for the effective compare-at amount, and
`effectiveWeight()` and `effectiveDimensions()` for shipping measurements.
Products without generated combinations use an automatically-created default
variant, so `$cart->add($product)` remains valid.

Variant combinations are identified by stable attribute and value IDs rather
than customer-facing labels. Duplicate combinations, SKUs, and barcodes are
rejected.

Attribute values must match their parent attribute type. Text attributes accept
strings, number attributes accept integers or floats, and boolean attributes
accept booleans.

```php
$giftWrap = ProductAttribute::create([
    'slug' => 'gift-wrap',
    'name' => 'Gift wrap',
    'type' => ProductAttributeType::Boolean,
]);

$giftWrap->values()->create([
    'slug' => 'yes',
    'name' => 'Yes',
    'value' => true,
]);
```

Use `withAttributeValues()` to load product attribute values with their parent
attribute when rendering a product page.

```php
$product = Product::query()
    ->withAttributeValues()
    ->where('slug', 'basic-plan')
    ->firstOrFail();

foreach ($product->attributeValues as $value) {
    $attributeName = $value->attribute->name;
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
