# Carts and orders need a sales context

Larasell now has sales channels: the places you sell, such as a storefront, a
POS or a marketplace. Carts and orders remember the `SalesContext` they were
made in (channel, locale and currency), in the new columns `sales_channel_id`
and `locale` next to `currency`. A channel without a URL, such as a POS, works
the same way.

A cart or order is created in a context you pass, with `createIn()`. It never
picks one up from whatever context happens to be current, so a cart made in a
command or job cannot silently land in the wrong channel. In a storefront
controller, inject the request's context. The migrations create a `default`
channel from `app.url`, the supported locales and the enabled currencies.

## Who is affected

Apps that create carts or orders themselves. Orders made by `Checkout` are not
affected: they take the cart's context.

## How to upgrade

Run the migrations, then create carts with their context:

```php
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\SalesChannels\SalesContext;

// Before
$cart = Cart::query()->create(['currency' => Currency::EUR]);

// After, in a storefront controller
public function store(SalesContext $context)
{
    $cart = Cart::createIn($context);
}

// After, anywhere else
$cart = Cart::createIn(SalesContext::for('default', 'en'));
```

`Cart::query()->create()` without a context now throws a `LogicException`. A
`currency` given in the attributes still wins over the context's, e.g.
`Cart::createIn($context, ['currency' => Currency::EUR])`.
