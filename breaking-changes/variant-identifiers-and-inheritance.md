# Identifiers live on variants, and variants inherit fields explicitly

Products no longer have `sku` and `barcode`; each variant has its own, plus a new `mpn`. A
variant now says per field whether it inherits the product's value, overrides it or clears it,
instead of treating a `null` column as "inherit". This keeps identifiers on the sellable unit and
lets a variant have no value where its product has one.

## Who is affected

Apps that read or write `Product::$sku`/`$barcode`, call `ProductVariant::effectiveSku()` or
`effectiveBarcode()`, or write a variant's `weight`, `dimensions` or `type`. The migration moves
the products' identifiers to their default variants and marks existing variant weights,
dimensions and types as overridden.

## Identifiers

```php
// Before
$product->update(['sku' => 'TEE-1']);
$variant->effectiveSku();

// After
$product->defaultVariant()->update(['sku' => 'TEE-1']);
$variant->sku;
```

## Overriding a field

`weight`, `dimensions`, `country_of_origin`, `hs_code` and `type` each have a
`<field>_inheritance` column (`inherited`, `overridden`, `cleared`, see
`Larasell\Larasell\Catalog\InheritanceState`). A value without `overridden` is rejected.

```php
// Before
$variant->update(['weight' => Weight::of(600, 'g')]);
$variant->update(['weight' => null]); // inherit

// After
$variant->override('weight', Weight::of(600, 'g'))->save();
$variant->inherit('weight')->save();
$variant->clear('weight')->save(); // no weight, whatever the product has
```

The default variant no longer gets copies of the product's weight and dimensions; it inherits
them. Read them with `effectiveWeight()` and `effectiveDimensions()` as before.
