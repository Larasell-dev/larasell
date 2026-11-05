# `Cart::add()`, `set()` and `remove()` take typed IDs instead of numbers

A bare number meant a product ID in `Cart::add()` but a cart line ID in
`Cart::set()` and `Cart::remove()`. Passing the wrong one changed an unrelated
line or added an unrelated product without any error. These methods now take a
`ProductId`, `VariantId` or `CartItemId`, so PHP rejects the mix-up.

## Who is affected

Apps that pass numbers to these methods. Passing a `Product` or
`ProductVariant` works as before.

## How to upgrade

```php
use Larasell\Larasell\Carts\CartItemId;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\VariantId;

// Before
$cart->add($request->integer('product'), 2);
$cart->set($request->integer('line'), 3);
$cart->remove($request->integer('line'));

// After
$cart->add(ProductId::fromString($request->input('product')), 2);
$cart->add(VariantId::fromString($request->input('variant')));
$cart->set(CartItemId::fromString($request->input('line')), 3);
$cart->remove(CartItemId::of($item));
```

`fromString()` throws `Larasell\Larasell\Ids\InvalidId` for input that is not a
positive whole number; validate request input before it reaches the cart.
