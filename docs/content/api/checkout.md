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
        $result = $this->checkout->create($cart, [
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

        if ($result->requiresRedirect()) {
            return $result->redirect();
        }

        return redirect()->route('orders.show', $result->order);
    }
}
```

`customer_id` is optional. Leave it `null` for guest checkout. The email,
name, addresses, product details, and prices are always copied to the order,
so later changes to customer or product records do not rewrite order history.

Both addresses accept an `Address` value object or an associative array. An
address requires `country`, `first_name`, `last_name`, `street`, `city`, and
`postcode`. `street` may be a string or an ordered array of lines. `title`,
`state`, `email`, `phone`, `company`, and `tax_id` are optional. All values are
stored in the order snapshot.

Order addresses always return `Address` value objects, regardless of which input form
was used:

```php
$result->order->shipping_address->street;
$result->order->shipping_address->country;
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
$result = $checkout->create($cart, $data, paymentMethod: 'cash');
```

Both built-in methods use the offline driver and create a pending payment. They
do not collect money or expose customer-facing payment instructions. The result
contains the created `$result->order`, `$result->payment`, and an optional
`$result->action`.

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

## Refunds

Successful payments can be refunded in full or in part. Omitting the amount
refunds the remaining amount that is not already refunded or reserved by a
pending refund:

```php
use Larasell\Larasell\Price;

$refund = $payment->refund();
$partialRefund = $payment->refund(Price::of(2500));
```

Cash and bank transfer refunds remain `pending` until their real-world transfer
is confirmed manually:

```php
$refund->markAsSucceeded();
// or: $refund->markAsFailed($message);
// or: $refund->cancel();
```

`$payment->refundedAmount()`, `$payment->pendingRefundAmount()`, and
`$payment->refundableAmount()` expose the financial totals. Pending refunds
reserve their amount so concurrent attempts cannot exceed the successful
payment. The original payment remains `succeeded`; refund records preserve the
separate money movement and its history.

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

Paid orders can only be cancelled after every successful payment has been fully
refunded. A refund never cancels an order automatically, and fulfilled orders
cannot be cancelled even after a full refund.
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
- `Larasell\Larasell\Events\RefundPending`
- `Larasell\Larasell\Events\RefundSucceeded`
- `Larasell\Larasell\Events\RefundFailed`
- `Larasell\Larasell\Events\RefundCancelled`

Each order event exposes an `$order` property and each payment event exposes a
`$payment` property. Refund events expose a `$refund` property. Idempotent
operations do not dispatch duplicate events.

```php
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Events\OrderPaid;

Event::listen(OrderPaid::class, function (OrderPaid $event) {
    // Handle the paid order through $event->order.
});
```

Custom providers must implement `Larasell\Larasell\Contracts\PaymentProvider`
and return a `Larasell\Larasell\Payments\PaymentResult` from `initiate()`.

## Custom payment providers

Checkout creates the local order and pending payment before invoking a provider.
The provider therefore receives stable models that can be included in provider
metadata and idempotency keys:

```php
use Larasell\Larasell\Contracts\PaymentProvider;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;
use Larasell\Larasell\Payments\RedirectPaymentAction;

final class HostedPaymentProvider implements PaymentProvider
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        $session = $this->client->createSession([
            'amount' => $request->payment->amount->amount(),
            'currency' => $request->order->currency->value,
            'success_url' => $request->option('success_url'),
            'metadata' => [
                'order_id' => $request->order->getKey(),
                'payment_id' => $request->payment->getKey(),
            ],
            'idempotency_key' => 'payment-'.$request->payment->getKey(),
        ]);

        return PaymentResult::pending(
            reference: $session->id,
            action: new RedirectPaymentAction($session->url),
        );
    }
}
```

Provider-specific checkout values can be passed as the fourth argument:

```php
$result = $checkout->create(
    $cart,
    $data,
    paymentMethod: 'hosted',
    paymentOptions: [
        'success_url' => route('checkout.success'),
        'cancel_url' => route('checkout.cancel'),
    ],
);
```

Providers return `PaymentResult::pending()`, `PaymentResult::succeeded()`, or
`PaymentResult::failed()`. Provider exceptions are recorded as failed payments.
For asynchronous providers, use the provider reference from a verified webhook:

```php
$payment = Payment::findByProviderReference('hosted', $providerReference);
$payment->markAsPaid();
// or: $payment->markAsFailed($message);
```

Return and cancellation URLs are storefront navigation only. They must not mark
a payment as paid; asynchronous provider webhooks are authoritative.

Providers that support refunds additionally implement
`Larasell\Larasell\Contracts\RefundProvider`. Its `refund()` method receives a
persisted payment and refund through `RefundRequest` and returns
`RefundResult::pending()`, `succeeded()`, `failed()`, or `cancelled()`. Providers
that only implement `PaymentProvider` continue to work, but their payments
cannot be refunded through Larasell.
