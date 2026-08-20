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
```

Use `set()` when you want to replace the quantity for a product
already in the cart.

```php
$cart->set($product, quantity: 5);
```

Use `remove()` or `clear()` to remove items.

```php
$cart->remove($product);
$cart->clear();
```

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

## Stock and backorders

Products allow backorders by default. When a product has
`allow_backorders` set to `false`, the cart rejects quantities greater
than the product's available `stock`. Products with `stock` set to
`null` do not have inventory limits and may be added in any quantity.
