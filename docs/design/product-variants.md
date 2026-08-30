# Product variants design proposal

Status: partially implemented

This document records the product variant design for Larasell. The catalog
foundation is implemented; cart, checkout, inventory, and order integration
remain future work.

## Objective

Keep `Product` as the catalog and merchandising entity while introducing a
first-class identity for each concrete purchasable item.

For example:

```text
Product: Classic T-shirt

Variants:
- Small / Black
- Medium / Black
- Medium / White
```

Each variant may have its own SKU, barcode, price, stock, availability, and
fulfillment data.

## Current behavior

Larasell currently supports reusable typed product attributes and values:

- `ProductAttribute`: Size, Color, Material
- `ProductAttributeValue`: Small, Black, Cotton
- Products can be associated with multiple attribute values.
- Product listings can be filtered by attached attribute values.

Products can now persist variant dimensions and concrete combinations. The
catalog layer validates combinations and resolves selections, but variants do
not yet participate in carts, checkout, inventory reservations, or orders.

Commerce data currently belongs to the product:

- Cart items reference `product_id` only.
- Price, stock, backorder policy, and quantity limits come from the product.
- A cart can contain only one line for a given product.
- Checkout locks and decrements product stock.
- Order items do not identify or snapshot an attribute combination.

Products now have nullable, unique `sku` and `barcode` fields. Checkout copies
them to `product_sku` and `product_barcode` on the order item. This is sufficient
while one product represents one purchasable item.

## Domain boundaries

Three related concepts should remain distinct.

### Variant dimensions

Predefined choices that resolve to a concrete purchasable item, such as Size
and Color. Their combination can affect SKU, price, stock, and fulfillment.

### Product attributes

Facts used for display or filtering that do not identify a purchasable item,
such as Material or Waterproof. These should not multiply variants.

### Line customizations

Customer-specific input such as engraving text, gift wrapping, or a gift
message. These belong to a cart or order line and should not create catalog
variants.

The same attribute definitions and values serve product filtering and variant
generation. Products attach every value that applies to them. Variant dimensions
separately identify which attached attributes form purchasable combinations.

## Proposed model

```text
Product
  belongs to many ProductAttributes as VariantDimensions
  has many ProductVariants

ProductVariant
  belongs to Product
  belongs to many ProductAttributeValues
```

`Product::generateVariants()` accepts persisted product attributes, records them
as variant dimensions, and generates their Cartesian product from the attribute
values already attached to the product. Generation must produce at least two
combinations and never silently deletes or overwrites existing variants.

Suggested `larasell_product_variants` fields:

```text
id
product_id
sku
barcode
price
stock
allow_backorders
min_quantity
max_quantity
status
position
combination_key
metadata
timestamps
```

Possible meanings:

- `sku`: nullable, unique merchant stock identifier.
- `barcode`: nullable, unique external identifier such as EAN, UPC, or GTIN.
- `price`: nullable if variants may inherit the product price.
- `stock`: nullable for untracked inventory.
- `allow_backorders`: variant-specific policy or nullable inheritance value.
- `min_quantity` and `max_quantity`: variant-specific purchase constraints.
- `status`: controls whether the concrete combination is purchasable.
- `position`: stable display ordering.
- `combination_key`: canonical identity for the selected attribute values.
- `metadata`: optional integration data that is not part of core behavior.

The variant/value pivot contains:

```text
product_variant_id
product_attribute_value_id
```

## Catalog invariants

The implementation must enforce the following rules:

1. A variant belongs to exactly one product.
2. A variant contains no more than one value from the same attribute.
3. Every selected value is available for the variant's parent product.
4. A product cannot contain the same attribute combination twice.
5. Variant SKUs and barcodes are unique when present.
6. A variant cannot be selected when it is disabled or incomplete.
7. Changing labels or translations does not change combination identity.

A canonical combination key can provide database-level duplicate protection:

```text
color:black|size:small
```

The key should be built from stable attribute and value identifiers, sorted by
attribute identity. It must not use translated customer-facing labels. A unique
constraint on `(product_id, combination_key)` prevents duplicate combinations.

Application validation is still required because ordinary pivot constraints
cannot prevent two values from the same attribute being attached to one variant.

## SKU and barcode identity

The database ID is the internal relational identity. The SKU is the stable
business-facing identity used by warehouses, ERP systems, invoices, imports,
exports, and support. A barcode is an optional external standardized identity.

Identifiers must be strings so leading zeroes are preserved. Neither SKU nor
barcode should be used as the primary key because merchant identifiers may be
renamed without breaking internal relationships.

For a variant product, each inventory-bearing combination should normally have
its own SKU:

| Product | Variant | SKU | Barcode |
| --- | --- | --- | --- |
| Classic T-shirt | Small / Black | `TSHIRT-BLK-S` | `4012345678901` |
| Classic T-shirt | Medium / Black | `TSHIRT-BLK-M` | `4012345678918` |

The existing product identifier fields remain useful for simple products and
as a migration source. A final design must choose one authoritative rule:

- Keep simple products variant-free and resolve identifiers from the product.
- Give every product an invisible default variant and always resolve identifiers
  from a variant.

The default-variant approach gives cart, inventory, checkout, and integrations
one purchasable entity type. It is the preferred long-term design, despite the
additional migration work.

## Simple products and compatibility

Existing calls should remain valid:

```php
$cart->add($product);
```

Under the preferred design, this resolves the product's default variant
internally. Existing products receive one default variant during migration.
Their SKU, barcode, price, stock, backorder policy, and quantity limits are
copied to it.

Compatibility helpers may continue exposing effective values through Product,
but the system must avoid two writable sources of truth. Product commerce fields
should eventually be deprecated after migration rather than independently
editable alongside default-variant fields.

## Variant resolution

The product should resolve a selection explicitly:

```php
$variant = $product->variantFor([
    'size' => 'small',
    'color' => 'black',
]);
```

Resolution should reject:

- Unknown attribute or value slugs.
- Values not assigned to the product.
- Incomplete selections.
- Values from two attributes represented as one attribute.
- Combinations for which no variant exists.
- Disabled or otherwise unavailable variants.

The result must be one persisted `ProductVariant`, not an ad hoc collection of
attribute values.

## Cart behavior

Cart items should identify the purchasable variant:

```text
cart_id
product_id
product_variant_id
quantity
```

With mandatory default variants, cart uniqueness becomes:

```text
unique(cart_id, product_variant_id)
```

This permits Small / Black and Medium / White to exist as separate lines while
repeated additions of Small / Black merge into the same line.

Proposed APIs:

```php
$cart->add($product);
$cart->add($variant, quantity: 2);
$cart->setQuantity($variant, 4);
$cart->remove($variant);
```

An alternative API may accept both product and variant, but it must verify that
the variant belongs to that product.

Commerce values should be resolved centrally rather than with fallback logic
scattered through the codebase:

```php
$item->unitPrice();
$item->sku();
$item->barcode();
$item->availableStock();
$item->allowsBackorders();
```

## Pricing and inventory

The selected variant is authoritative for price and inventory. Checkout must
lock the variant row inside its transaction:

```sql
select * from larasell_product_variants
where id = ?
for update
```

After locking, checkout revalidates:

- The variant still belongs to the product.
- The variant is still purchasable.
- The requested quantity satisfies its limits.
- Sufficient stock remains unless backorders are allowed.
- The cart's currency and current price are valid.

Stock deduction and restoration must target the variant. Inventory reservations
therefore need `product_variant_id`; retaining only `product_id` makes restocking
ambiguous.

Concurrency tests must prove that two checkouts cannot purchase the final unit
of one variant and that stock operations on different variants do not block or
modify each other unnecessarily.

## Order snapshots

Order items should record both an optional current relation and immutable
transaction data:

```text
product_variant_id
product_sku
product_barcode
variant_name
variant_attributes JSON
```

Example attribute snapshot:

```json
{
  "size": {
    "attribute_slug": "size",
    "attribute_name": "Size",
    "value_slug": "small",
    "value_name": "Small"
  },
  "color": {
    "attribute_slug": "color",
    "attribute_name": "Color",
    "value_slug": "black",
    "value_name": "Black"
  }
}
```

Labels must be copied because catalog labels and translations may change.
Stable slugs or IDs should also be recorded for reporting. The relation should
be nullable and use `nullOnDelete()` so deleting catalog data never deletes or
invalidates order history.

The existing `product_sku` and `product_barcode` order fields can remain. They
snapshot whichever concrete purchasable item supplied those identifiers.

## Promotions and shipping

Promotion targets may eventually need to distinguish:

- A product and all of its variants.
- One specific variant.
- Variants containing an attribute value.

Promotion and discount allocation identities must remain stable after cart
lines become variant-specific.

Shipping integrations may require variant-level weight and dimensions. These
fields should be added when a concrete shipping contract is designed, rather
than guessing units or packaging behavior as part of the initial variant work.
Likely fields include integer weight with a configured unit and dimensions with
an explicit unit.

## Payments and external integrations

Payments do not require an SKU to create a payment operation. Larasell should
remain authoritative for catalog and fulfillment data. Payment integrations may
include order and line identifiers in provider metadata where useful, but must
respect provider metadata size limits.

ERP, warehouse, and fulfillment integrations should use the variant database ID
for internal relations and the snapshotted SKU for business-level exchange.

## Administrative behavior

A usable variant editor should support:

- Selecting the attributes that define variants.
- Generating the Cartesian product of selected values.
- Enabling only combinations that are actually sold.
- Bulk editing price, stock, status, SKU, and barcode.
- Detecting duplicate SKUs, barcodes, and combinations before saving.
- Assigning images to a variant when product-level images are insufficient.

Variant generation must not silently delete existing variants, inventory, or
order relationships when attribute values change.

## Implementation plan

The feature should be delivered as a complete cart-to-order vertical slice. A
database model that cannot safely enter a cart is not a useful public feature.

### Phase 1: failing tests and domain rules

Add tests for:

- Creating valid combinations.
- Rejecting two values from the same attribute.
- Rejecting values unavailable to the product.
- Rejecting duplicate combinations.
- Unique non-null SKUs and barcodes.
- Resolving a selection independently of input ordering.
- Rejecting incomplete and unavailable selections.

### Phase 2: catalog foundation

- Add the variant migration and model.
- Register the model in `ModelRegistry`.
- Add product and attribute-value relationships.
- Implement canonical combination keys and validation.
- Implement variant resolution.

### Phase 3: cart integration

- Add variant identity to cart items.
- Merge only identical variants.
- Permit different variants of one product as separate lines.
- Resolve price and quantity rules from the variant.
- Preserve the simple product API through default variants.

### Phase 4: checkout and inventory

- Lock variants deterministically.
- Revalidate variant availability and quantity after locking.
- Deduct and reserve variant stock.
- Restore the correct variant stock on cancellation or expiration.
- Include variant identity in checkout idempotency fingerprints.

### Phase 5: order history

- Add nullable variant relations to order items.
- Snapshot SKU, barcode, price, and selected attribute labels.
- Verify snapshots survive catalog changes and deletion.

### Phase 6: surrounding systems

- Make promotions aware of variant-specific cart lines.
- Make shipping contexts expose the selected variant.
- Update events and integration payloads.
- Add admin variant management.
- Document migration and public APIs.

## Required test coverage

At minimum, the completed feature needs tests for:

- Simple products remaining backward compatible.
- Two variants of one product producing two cart lines.
- Adding the same variant twice merging quantities.
- Variant-specific prices and quantity limits.
- Variant-specific stock and backorder policy.
- Concurrent checkout of the final variant unit.
- Cancellation and expiration restoring the correct variant.
- Checkout idempotency distinguishing different variant selections.
- Product and variant changes not rewriting order snapshots.
- Deleted variants not breaking historical orders.
- Promotions allocating discounts to the correct variant line.
- Model overrides through `ModelRegistry`.
- SQLite, MySQL, and PostgreSQL behavior.

## Open decisions

The following decisions should be settled before implementation:

1. Whether every product always has a default variant.
2. Whether variant price and policies inherit from the product or are always
   explicit.
3. How existing product commerce fields are deprecated and migrated.
4. Whether variant images are required in the first release.
5. Whether SKU uniqueness spans products and variants in one shared namespace.
6. Whether archived variants may be reactivated with changed combinations.
7. Which weight and dimension units future shipping APIs will use.

The recommended defaults are: mandatory default variants, one authoritative
variant commerce record, stable canonical combination keys, immutable order
snapshots, and SKU uniqueness across all purchasable items.
