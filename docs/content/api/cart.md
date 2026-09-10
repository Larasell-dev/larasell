---
title: Cart API
description: Create carts, add products, update quantities, and calculate totals.
---

# Cart API

The cart model provides a small API for collecting products before
checkout. Each cart has one currency, supplied when the cart is created.

Cart items store the selected product and quantity. Totals are calculated
from the product's current price.

```php
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Price;

$cart = Cart::create([
    'currency' => Currency::USD,
    'session_id' => session()->getId(),
]);

$product = Product::query()->where('slug', 'basic-plan')->firstOrFail();

$item = $cart->add($product, quantity: 2);
```

## Updating a cart

Use `add()` to add a new product or increase the quantity of an
existing cart item.

```php
$cart->add($product);
$cart->add($product, quantity: 2);
$cart->add($product->getKey(), quantity: 2);
```

An integer passed to `add()` is resolved as a product ID through the configured
product model. Variant IDs remain explicit `ProductVariant` instances.

Use `set()` when you want to replace the quantity for a product
already in the cart.

```php
$cart->set($product, quantity: 5);
$cart->set($item->getKey(), quantity: 5);
```

Passing a cart item ID updates that specific line while preserving its metadata.
The lookup is scoped to the cart.

Use `remove()` or `clear()` to remove items.

```php
$cart->remove($product);
$cart->remove($item->getKey());
$cart->clear();
```

Passing a cart item ID removes that specific line. The lookup is scoped to the
cart, so an item belonging to another cart is left untouched.

## Merging carts

Use `merge()` when a shopper has a guest cart and then signs in. The cart you
call `merge()` on is the destination and survives. The source cart is copied
into the destination and deleted after the merge succeeds.

```php
use Larasell\Larasell\Carts\Strategies\CombineQuantities;
use Larasell\Larasell\Carts\Strategies\ReplaceDestination;

$result = $accountCart->merge($guestCart);
$result = $accountCart->merge($guestCart, new CombineQuantities());
$result = $accountCart->merge($guestCart, new ReplaceDestination());

$cart = $result->cart;
$adjustedLines = $result->adjustedLines;
$skippedLines = $result->skippedLines;
```

The default strategy combines matching lines by summing quantities. Lines match
when they have the same product variant and the same normalized metadata.
Different metadata stays as separate lines.

The merger also combines cart-level attributes. Promotion codes are unioned and
revalidated, metadata is shallow-merged with destination keys winning, and the
selected shipping option is kept only if it is still available after the lines
have been merged.

Built-in strategies cover common storefront decisions:

- `CombineQuantities` sums matching quantities and appends source-only lines.
- `PreferDestination` keeps destination quantities for matching lines and
  appends source-only lines.
- `PreferSource` replaces matching quantities with the source quantities and
  keeps destination-only lines.
- `ReplaceDestination` clears the destination and copies the source cart.
- `KeepDestination` discards source lines after any cart-level attribute merge.
- `FailOnConflict` wraps another strategy and throws the existing cart quantity
  exceptions instead of clamping or skipping lines.

If a combined quantity exceeds stock or a maximum quantity, the default behavior
is to clamp to the allowed quantity and record the change on
`$result->adjustedLines`. If a line is no longer purchasable, it is skipped and
recorded on `$result->skippedLines`.

You can also pass a custom strategy object or callback:

```php
use Larasell\Larasell\Carts\CartMergeContext;
use Larasell\Larasell\Carts\CartMergePlan;

$accountCart->merge($guestCart, function (CartMergeContext $context): CartMergePlan {
    // Return a custom line and attribute plan.
});
```

### Login integration

Larasell does not hook into authentication automatically. Your storefront owns
when to merge and which cart should survive. A blank storefront typically keeps
the current cart ID in the session while browsing:

```php
$cart = Cart::query()->find(session('cart_id'))
    ?? Cart::query()->create(['currency' => Currency::EUR]);

session(['cart_id' => $cart->id]);
$cart->add($product);
```

After a successful login or registration, resolve the guest session cart and the
saved account cart, then claim or merge:

```php
use Illuminate\Http\Request;
use Larasell\Larasell\Models\Cart;

public function store(Request $request)
{
    $request->authenticate();
    $request->session()->regenerate();

    $user = $request->user();
    $guest = Cart::query()->find($request->session()->get('cart_id'));
    $saved = Cart::query()
        ->where('user_id', $user->id)
        ->latest('id')
        ->first();

    if ($guest !== null && $saved !== null && $guest->isNot($saved)) {
        $cart = $saved->merge($guest)->cart;
    } elseif ($guest !== null) {
        $guest->forceFill(['user_id' => $user->id])->save();
        $cart = $guest;
    } else {
        $cart = $saved;
    }

    if ($cart !== null) {
        $request->session()->put('cart_id', $cart->id);
    }

    return redirect()->intended('/');
}
```

Merging carts with different currencies throws an exception. Drop session carts
that are already owned by another user before merging them into the current
shopper's account.

## Reading a cart

Load `items.product` to render a cart with product details.

```php
$cart->load('items.product');

foreach ($cart->items as $item) {
    $item->product->name;
    $item->quantity;
    $item->total();
}
```

You can also get the total quantity and price for the cart.

```php
$quantity = $cart->quantity();
$total = $cart->total();
$formattedTotal = $total === null ? null : Price::format($total, $cart->currency);
```

Empty carts return `null` from `total()`.

## Cart metadata

Use metadata for application-specific information that applies to the whole
cart, such as delivery instructions or a table number.

```php
$cart->metadata->put('delivery_instructions', 'Leave at reception');
$cart->metadata->put('table_number', 12);

$instructions = $cart->metadata->get('delivery_instructions');
$fallback = $cart->metadata->get('missing_key', 'Not provided');

$cart->metadata->forget('table_number');
$cart->save();
```

Metadata is a Laravel collection backed by a JSON column. Collection mutations
are persisted when the cart is saved. You can also replace the complete value
when creating or updating a cart. Metadata is copied to the order during
checkout.

```php
$cart->update([
    'metadata' => ['delivery_instructions' => 'Leave at reception'],
]);
```

Metadata is informational. Prices, discounts, inventory, and other trusted
commerce data should be calculated by server-side APIs instead.

## Cart item metadata

Pass metadata as the third argument to `add`, or as the named `metadata`
argument, for application-specific information that belongs to one cart line:

```php
$item = $cart->add($burger, quantity: 1, metadata: [
    'belongs_to' => 'Alice',
    'customizations' => [
        'without' => ['onions'],
        'extra' => ['cheese'],
    ],
]);
```

Metadata is part of a cart line's identity. Adding the same variant with the
same metadata increases the existing line quantity. Different metadata creates
a separate line, so two people can order differently customized versions of
the same product. `set` and `remove` accept the same optional metadata argument
to address the matching line.

Cart item metadata is copied to the order item during checkout. Use it for
descriptive application data. A customization that changes price, inventory,
tax, or purchasing rules should be modeled and validated by the application as
a product variant, add-on product, or another structured commerce concept.

## Shipping methods

Shipping methods receive the cart and may register one or more options. Each
option needs a unique handle, a customer-facing name, and a price.

Use `purchasableItems()` when rates depend on the selected combinations. It
returns cart items with their product, concrete variant, and variant attribute
values loaded.

```php
use Larasell\Larasell\Enums\WeightUnit;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingMethod;
use Larasell\Larasell\Weight;

class ParcelShipping extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $items = $cart->purchasableItems();
        $variants = $items->pluck('variant');

        $this->register('standard', 'Standard shipping', Price::of(500));

        if ($cart->quantity() < 10) {
            $this->register('express', 'Express shipping', Price::of(1200));
        }

        $weight = $cart->weight();

        if ($weight !== null && $weight->greaterThan(Weight::of(2, WeightUnit::Kilogram))) {
            $this->register('heavy', 'Heavy parcel', Price::of(1800));
        }
    }
}
```

Register the method in your application's service provider:

```php
use Larasell\Larasell\Shipping\ShippingManager;

public function boot(ShippingManager $shipping): void
{
    $shipping->register(ParcelShipping::class);
}
```

Read the available options and persist the customer's selection on the cart:

```php
$options = $cart->shippingOptions();
$cart->selectShippingOption('express');

$cart->shippingOption(); // The selected ShippingOption
$cart->subtotal();       // Products only
$cart->weight();         // Sum of line weights, or null when any line is missing one
$cart->discountTotal();  // Applied product and shipping discounts
$cart->total();          // Products + shipping - discounts
```

The selected option is resolved again whenever totals are calculated. Checkout
stores a snapshot of its method, handle, name, and price on the order.

See the [Promotions API](/api/promotions) for defining automatic discounts and
reading their allocations.

See [Taxes](/api/taxes) for classifying products, showing calculated or
provisional cart tax estimates, and handling unavailable totals.

## Stock and backorders

Products allow backorders by default. When a product has
`allow_backorders` set to `false`, the cart rejects quantities greater
than the product's available `stock`. Products with `stock` set to
`null` do not have inventory limits and may be added in any quantity.
