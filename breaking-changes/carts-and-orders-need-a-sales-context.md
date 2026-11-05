# Carts and orders need a sales context

Larasell now has sales channels: the places you sell, such as a storefront, a
POS or a marketplace. Carts and orders remember the `SalesContext` they were
made in (channel, locale and currency), in the new columns `sales_channel_id`
and `locale` next to `currency`. A channel without a URL, such as a POS, works
the same way.

A new cart or order takes the current context: a storefront request's (set by
middleware from the request's host and prefix), or the one a queued job
inherited from it. Outside both, creating one without a context throws a
`LogicException`. The migrations create a `default` channel from `app.url`, the
supported locales and the enabled currencies.

## Who is affected

Apps that create carts or orders outside a storefront request, such as in
console commands, seeders, imports, or tests. Carts made in storefront requests
and orders made by `Checkout` are not affected.

## How to upgrade

Run the migrations, then pass a context where there is no request:

```php
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\SalesChannels\SalesContext;

// Before
$cart = Cart::query()->create(['currency' => Currency::EUR]);

// After: for one record
$cart = Cart::query()->create(['sales_context' => SalesContext::for('default', 'en')]);

// After: for everything a command or test does from here on
SalesContext::for('default', 'en')->activate();
$cart = Cart::query()->create();
```

A `currency` given explicitly still wins over the context's currency. In a
Pest suite, activating the default context in a `beforeEach` keeps existing
tests working:

```php
uses()->beforeEach(fn () => SalesContext::for('default', 'en')->activate())->in('Feature');
```
