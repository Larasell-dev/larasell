# Variants are made from options only

The attribute-based way to make variants is gone. Global options (`OptionDefinition` with its
`OptionDefinitionValue`s) replaced it, and having two ways to make variants confused callers
about which one a product used.

Removed:

- `Product::variantDimensions()`, `generateVariants()`, `createVariant()` and `variantFor()`
- The actions `Larasell\Larasell\Catalog\Actions\GenerateProductVariants` and
  `CreateProductVariant`
- `ProductVariant::attributeValues()` and `ProductAttributeValue::variants()`, which read the
  attribute values a variant was made of
- The `larasell_product_variant_dimensions` and `larasell_product_variant_product_attribute_value`
  tables. The migration `move_variant_dimensions_to_options` already moved their data to options;
  the new `drop_product_variant_dimensions` migration drops them afterwards.
- `ProductVariant::options()` no longer falls back to a variant's attribute values. It returns the
  variant's option values, or none.

Attributes that describe a variant stay: read them with `ProductVariant::effectiveAttributeValues()`
and change them with `overrideAttribute()` and `inheritAttribute()`.
`PromotionContext::withAttributeValue()` now matches a line's effective attribute values.

## Who is affected

Apps that call any of the removed methods, relations or actions, eager load
`attributeValues` on variants, or read the dropped tables. Apps that already make variants with options are not
affected. Run `php artisan migrate` to drop the table.

## How to upgrade

Give the product options and create the combinations you sell. To change a product's options
in one go, use `Larasell\Larasell\Catalog\Actions\SyncProductOptions`; to save an option with its
values, `Larasell\Larasell\Catalog\Options\Actions\SaveOptionDefinition`.

```php
// Before
$product->generateVariants([$size, $color]);
$variant = $product->createVariant([$small, $black], ['sku' => 'TEE-BLK-S']);
$variant = $product->variantFor(['size' => 'small', 'color' => 'black']);

// After
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;

$size = OptionDefinition::query()->where('slug', 'size')->sole();
$color = OptionDefinition::query()->where('slug', 'color')->sole();
$small = $size->values()->where('slug', 'small')->sole();
$black = $color->values()->where('slug', 'black')->sole();

$product->addOption($size, [$small]);
$product->addOption($color, [$black]);
[$variant] = $product->createCombinations([[$small, $black]]);
$variant->update(['sku' => 'TEE-BLK-S']);

// Reading what a variant is made of
$variant->options(); // keyed by option slug
$variant->effectiveAttributeValues(); // the attributes that describe it, not its options

// Finding a variant by its values
$variant = $product->variants()->get()->first(
    fn ($variant) => collect($variant->options())->pluck('value_slug', 'attribute_slug')->all()
        == ['size' => 'small', 'color' => 'black'],
);
```

`createCombinations()` creates variants hidden; set their `status` to show them.
