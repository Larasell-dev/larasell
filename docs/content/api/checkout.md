---
title: Checkout API
description: Turn a cart into an immutable order and collect payment.
---

# Checkout API

Checkout creates an order from a cart, snapshots its customer, address,
product, and price data, reserves finite stock, and asks the configured
payment provider to initiate payment for the total.

The cart's currency becomes the order currency and is passed to the payment
provider alongside the currency-independent price amount.

Call the checkout action from your application's controller after validating
the request:

```php
use Illuminate\Http\Request;
use Larasell\Larasell\Address;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Models\Cart;

class CheckoutController
{
    public function __construct(private Checkout $checkout) {}

    public function __invoke(Request $request, Cart $cart)
    {
        $order = $this->checkout->create($cart, [
            'customer_email' => $request->string('email')->toString(),
            'customer_name' => $request->string('name')->toString(),
            'billing_address' => new Address(
                country: $request->string('billing_address.country')->toString(),
                firstName: $request->string('billing_address.first_name')->toString(),
                lastName: $request->string('billing_address.last_name')->toString(),
                street: $request->array('billing_address.street'),
                city: $request->string('billing_address.city')->toString(),
                postcode: $request->string('billing_address.postcode')->toString(),
            ),
            'shipping_address' => $request->array('shipping_address'),
            'customer_id' => $request->user()?->getKey(),
        ], paymentMethod: 'bank_transfer');

        // Return your application's confirmation response.
    }
}
```

`customer_id` is optional. Leave it `null` for guest checkout. The email,
name, addresses, product details, and prices are always copied to the order,
so later changes to customer or product records do not rewrite order history.

Both addresses accept an `Address` value object or an associative array. An
address requires `country`, `first_name`, `last_name`, `street`, `city`, and
`postcode`. `street` may be a string or an ordered array of lines. `title`,
`state`, `email`, and `phone` are optional.

Orders always return `Address` value objects, regardless of which input form
was used:

```php
$order->shipping_address->street;
$order->shipping_address->country;
```

## Order status

New orders start as `pending_payment`. The built-in cash and bank transfer
methods remain pending until payment is recorded manually. Supported order
transitions are:

- `pending_payment` to `paid`, `payment_failed`, or `cancelled`
- `payment_failed` to `pending_payment` or `cancelled`
- `paid` to `fulfilled` or `cancelled`

Use the model method to enforce these transitions:

```php
use Larasell\Larasell\Enums\OrderStatus;

$order->transitionTo(OrderStatus::Fulfilled);
```

## Payment methods

Cash is the default method. Pass `cash` or `bank_transfer` as the third checkout
argument to select a method explicitly:

```php
$order = $checkout->create($cart, $data, paymentMethod: 'cash');
```

Both built-in methods use the offline driver and create a pending payment. They
do not collect money or expose customer-facing payment instructions.

The available methods and default can be changed in the published configuration:

```php
use Larasell\Larasell\Payments\OfflinePaymentProvider;

'payments' => [
    'default' => 'cash',
    'methods' => [
        'cash' => [
            'driver' => 'offline',
            'provider' => OfflinePaymentProvider::class,
        ],
        'bank_transfer' => [
            'driver' => 'offline',
            'provider' => OfflinePaymentProvider::class,
        ],
    ],
],
```

## Recording payment

Use the payment model when an offline payment is received:

```php
$payment = $order->payments()->where('status', 'pending')->firstOrFail();
$payment->markAsPaid();
```

This atomically marks the payment as `succeeded`, records `paid_at`, and moves
the order to `paid`. Repeating the action is safe and does not replace the
original payment timestamp. The Larasell admin order page exposes the same
operation for pending payments.

A pending attempt can instead be cancelled with `$payment->cancel()`. Cancellation
only changes the payment to `cancelled`; it does not cancel the order or restore
stock.

## Cancelling an order

Unpaid orders can be cancelled directly. Pending payments are cancelled and
inventory deducted during checkout is restored by default.

```php
$order->cancel();
```

Pass `restock: false` when the inventory should remain deducted:

```php
$order->cancel(restock: false);
```

Paid orders cannot be cancelled until the application has handled their refund.
Order items store the quantity actually deducted from finite inventory, so
cancellation does not infer restocking from the product's current settings.

## Lifecycle events

Larasell dispatches dedicated events after their database transaction commits:

- `Larasell\Larasell\Events\OrderPlaced`
- `Larasell\Larasell\Events\OrderPaid`
- `Larasell\Larasell\Events\OrderFulfilled`
- `Larasell\Larasell\Events\OrderCancelled`
- `Larasell\Larasell\Events\PaymentPending`
- `Larasell\Larasell\Events\PaymentSucceeded`
- `Larasell\Larasell\Events\PaymentFailed`
- `Larasell\Larasell\Events\PaymentCancelled`

Each order event exposes an `$order` property and each payment event exposes a
`$payment` property. Idempotent operations do not dispatch duplicate events.

```php
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Events\OrderPaid;

Event::listen(OrderPaid::class, function (OrderPaid $event) {
    // Handle the paid order through $event->order.
});
```

Custom providers must implement `Larasell\Larasell\Contracts\PaymentProvider`
and return a `Larasell\Larasell\Payments\PaymentResult` from `initiate()`.
