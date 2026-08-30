---
title: Promotions API
description: Define automatic and code-based promotions, allocate discounts, and read discount totals.
---

# Promotions API

Promotions contain the rules that decide whether a cart receives a discount.
A promotion returns a `DiscountResult` when it applies and `null` when it does
not. Automatic promotions are evaluated whenever cart discounts or totals are
calculated and again during checkout. Code-based promotions are evaluated only
after their code has been attached to the cart.

Discount amounts are represented in minor currency units. A value of `500`
therefore represents EUR 5.00 for a cart using euros.

## Defining a promotion

Implement the `Promotion` contract in your application. The context contains
the cart, its loaded items, currency, merchandise subtotal, selected shipping
option, and the proportional discount allocator.

```php
<?php

namespace App\Promotions;

use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Price;

final class LargeOrderDiscount implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        if ($context->subtotal->amount() < 10_000) {
            return null;
        }

        return new DiscountResult(
            identifier: 'large-order-discount',
            name: 'Large order discount',
            allocations: $context->fixedAmountOff(Price::of(1_000)),
        );
    }
}
```

The identifier must be unique across all results returned for one calculation.
Use a stable identifier because it is stored in the order snapshot. The name is
customer-facing snapshot data and may be changed for future orders without
rewriting existing orders.

## Registering promotions

Register promotion classes in an application service provider. The manager
resolves each class through Laravel's container, so constructor dependencies
can be injected normally.

```php
use App\Promotions\LargeOrderDiscount;
use Larasell\Larasell\Discounts\PromotionManager;

public function boot(PromotionManager $promotions): void
{
    $promotions->register(LargeOrderDiscount::class);
}
```

Registering the same class more than once has no effect.

## User-entered promotion codes

Implement `HasCode` alongside `Promotion` when a customer must enter a code
before the promotion runs. A coded promotion uses the same context, results,
and allocation helpers as an automatic promotion.

```php
<?php

namespace App\Promotions;

use Larasell\Larasell\Contracts\Promotions\HasCode;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;

final class SaveTenPercent implements HasCode, Promotion
{
    public function code(): string
    {
        return 'SAVE10';
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        if ($context->subtotal->amount() < 5_000) {
            return null;
        }

        return new DiscountResult(
            identifier: 'save-ten-percent',
            name: 'Save ten percent',
            allocations: $context->percentageOff(10),
        );
    }
}
```

Register the class with `PromotionManager` in the same way as an automatic
promotion. Registration makes the code available; it does not apply it to every
cart.

Storefront code can attach, list, and remove codes through the cart:

```php
$cart->applyPromotionCode(' save10 ');
$cart->promotionCodes(); // ['SAVE10']

$cart->removePromotionCode('SAVE10');
```

Codes are trimmed and converted to uppercase. Attaching the same normalized
code more than once has no effect. Code values must be non-empty and unique
across all registered coded promotions.

`applyPromotionCode()` throws `InvalidArgumentException` when the normalized
code is not registered or its promotion does not currently return a positive
discount. Storefront controllers should turn that exception into an error for
the code input.

```php
use Illuminate\Http\Request;
use Larasell\Larasell\Models\Cart;

public function store(Request $request, Cart $cart)
{
    $data = $request->validate(['code' => ['required', 'string']]);

    try {
        $cart->applyPromotionCode($data['code']);
    } catch (\InvalidArgumentException $exception) {
        return back()->withErrors(['code' => $exception->getMessage()]);
    }

    return back();
}
```

An attached code is persisted on the cart, but its promotion is reevaluated
whenever discounts are calculated. If the customer changes the cart so that a
minimum subtotal or another rule is no longer satisfied, the code remains
attached but produces no discount. It starts applying again if the cart later
satisfies the rule. Use `removePromotionCode()` when the customer explicitly
removes it.

## Discount effects

`PromotionContext` provides helpers for the common ways to create allocations:

```php
// EUR 5.00 distributed proportionally across all cart lines.
$context->fixedAmountOff(Price::of(500));

// 10% distributed proportionally across all cart lines.
$context->percentageOff(10);

// EUR 3.00 off the selected shipping option.
$context->fixedAmountOffShipping(Price::of(300));

// 100% off the selected shipping option.
$context->percentageOffShipping(100);
```

Percentages may be integers or decimal strings such as `'12.5'`. Calculated
amounts are rounded down to whole minor units, and proportional allocation
distributes any remainder deterministically.

Product discounts can be restricted with a callback that receives each cart
item:

```php
allocations: $context->percentageOff(
    20,
    fn ($item) => $item->product->slug === 'featured-product',
),
```

A shipping helper returns no allocations when the cart has no selected shipping
option. Fixed discounts are capped at the eligible amount.

## Custom allocations

For rules that do not fit the helpers, create allocations directly. Targets
must belong to the evaluated cart.

```php
use Larasell\Larasell\Discounts\DiscountAllocation;

$item = $context->items->first();

return new DiscountResult(
    identifier: 'first-line-discount',
    name: 'First line discount',
    allocations: [
        new DiscountAllocation(
            target: $context->target($item),
            amount: Price::of(250),
        ),
    ],
);
```

An allocation amount must be positive. A result may contain at most one
allocation for each target. The promotion manager rejects targets outside the
cart.

## Reading cart discounts

The cart exposes the results and calculated totals:

```php
$cart->subtotal();      // Merchandise before discounts
$cart->discounts();     // Collection of DiscountResult objects
$cart->discountTotal(); // Effective discount amount
$cart->total();         // Merchandise + shipping - discounts
```

Each result contains its identifier, name, allocations, total, and an optional
normalized code:

```php
foreach ($cart->discounts() as $discount) {
    $discount->identifier;
    $discount->name;
    $discount->code; // null for automatic promotions
    $discount->total();

    foreach ($discount->allocations as $allocation) {
        $allocation->target;
        $allocation->amount;
    }
}
```

Promotions are recalculated from current cart data on every call. Do not treat a
cart result as a historical record.

## Stacking promotions

Promotions are stackable by default. Implement `HasPriority` to evaluate a
promotion before lower-priority promotions:

```php
use Larasell\Larasell\Contracts\Promotions\HasPriority;

final class SummerSale implements HasPriority, Promotion
{
    public function priority(): int
    {
        return 100;
    }

    // ...
}
```

Higher numeric priorities run first. Promotions without this interface have a
priority of `0`, and registration order resolves equal priorities. Earlier
promotions receive their allocations first. The manager caps the combined
discount at each product or shipping target, so later promotions cannot make
that target negative.

Implement the `ShouldBeExclusive` marker interface when a promotion must be
used alone:

```php
use Larasell\Larasell\Contracts\Promotions\ShouldBeExclusive;

final class WelcomeOffer implements Promotion, ShouldBeExclusive
{
    // ...
}
```

When one or more exclusive promotions apply, only the applicable exclusive
promotion with the highest priority is used. Registration order resolves a tie.
An inapplicable exclusive promotion does not affect other promotions.

## Availability windows

Implement `HasAvailability` when a promotion may only apply during a specific
period. The single `window()` method returns its optional boundaries:

```php
use Illuminate\Support\Carbon;
use Larasell\Larasell\Contracts\Promotions\HasAvailability;

final class SeptemberSale implements HasAvailability, Promotion
{
    public function window(): array
    {
        return [
            'starts_at' => Carbon::parse('2026-09-01 00:00:00'),
            'ends_at' => Carbon::parse('2026-09-30 23:59:59'),
        ];
    }

    // ...
}
```

Both boundaries are inclusive and must implement `CarbonInterface`. Either
`starts_at` or `ends_at` may be omitted for an open-ended window, but at least
one boundary is required. Coded promotions outside their availability window
cannot be attached to a cart. Previously attached codes remain stored but stop
producing a discount while their promotion is unavailable.

## Redemption limits

Implement `HasRedemptionLimit` to cap how many orders can use a promotion:

```php
use Larasell\Larasell\Contracts\Promotions\HasRedemptionLimit;

final class LimitedSummerSale implements HasRedemptionLimit, Promotion
{
    public function limit(): int|array
    {
        return 100;
    }

    // ...
}
```

A plain integer defines a global limit. Return an array to define global and
per-customer limits together:

```php
public function limit(): int|array
{
    return [
        'global' => 1000,
        'customer' => 1,
    ];
}
```

Either array key may be omitted, so `['customer' => 1]` creates a
customer-only limit. All configured limits must be positive integers.

By default, Larasell identifies a customer by `customer_id` when checkout
provides one, and otherwise by normalized email address. Applications can
replace this behavior by binding the resolver contract:

```php [app/Providers/AppServiceProvider.php]
use App\Commerce\PromotionCustomerResolver;
use Larasell\Larasell\Contracts\Promotions\PromotionCustomerResolver as PromotionCustomerResolverContract;

$this->app->bind(
    PromotionCustomerResolverContract::class,
    PromotionCustomerResolver::class,
);
```

The custom resolver implements:

```php
public function resolve(?int $customerId, string $email): string;
```

It must return a stable, non-empty identifier for the customer.

Checkout reserves one use of each applied limited promotion. A successful
payment permanently redeems it. Cancelling an unpaid order releases its
reserved uses. Redeemed uses remain counted when an order is later refunded or
cancelled.

Expired promotion reservations are processed by this command:

```bash
php artisan larasell:release-expired-promotions
```

The package does not schedule the command. Add it to the application scheduler:

```php [routes/console.php]
use Illuminate\Support\Facades\Schedule;

Schedule::command('larasell:release-expired-promotions')
    ->everyMinute()
    ->withoutOverlapping();
```

An expired promotion reservation cancels its unpaid order and releases its
reserved capacity. The reservation lifetime comes from the selected payment
method's `inventory_reservation_minutes` setting. The command supports a
custom batch size with `--batch-size=250`.

## Order snapshots

Checkout recalculates promotions inside its database transaction. The order
stores the final `discount_total` and a JSON snapshot in `discounts`. Each order
item stores its allocated `discount_total`.

```php
$order->subtotal;                 // Merchandise before discounts
$order->discount_total;           // Product and shipping discounts
$order->total;                    // Final amount charged
$order->items[0]->discount_total; // Discount allocated to this line
$order->discounts;                // Promotion and allocation snapshots
```

Cart-line targets are translated to permanent order item IDs in the snapshot.
Shipping allocations use the `shipping` target and have no order item ID. These
values remain unchanged when promotion classes or products change later. A
snapshot created by a coded promotion also contains its normalized `code`;
automatic promotion snapshots omit that key.

## Events

Promotion lifecycle events are dispatched after their database transaction
commits:

- `Larasell\Larasell\Events\PromotionCodeApplied`
- `Larasell\Larasell\Events\PromotionCodeRemoved`
- `Larasell\Larasell\Events\PromotionApplied`
- `Larasell\Larasell\Events\PromotionRedemptionReserved`
- `Larasell\Larasell\Events\PromotionRedemptionRedeemed`
- `Larasell\Larasell\Events\PromotionRedemptionReleased`

Code events expose the affected `$cart` and normalized `$code` properties. They
only fire when the stored codes actually change, so applying an attached code
or removing a missing code does not dispatch a duplicate event.

`PromotionApplied` exposes the `$order` and its `$discount` snapshot. One event
is dispatched for each promotion recorded during checkout. Reading
`$cart->discounts()` does not dispatch events because cart promotions are
recalculated values rather than durable state changes.

Redemption events expose the affected redemption through their `$redemption`
property. They are dispatched once when limited promotion capacity is reserved
during checkout, permanently redeemed by successful payment, or released by
order cancellation or expiration.
