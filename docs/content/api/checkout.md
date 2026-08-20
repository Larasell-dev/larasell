---
title: Checkout API
description: Turn a cart into an immutable order and collect payment.
---

# Checkout API

Checkout creates an order from a cart, snapshots its customer, address,
product, and price data, reserves finite stock, and asks the configured
payment provider to collect the total.

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
        ]);

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

New orders start as `pending_payment`. Checkout moves them to `paid` or
`payment_failed` based on the payment result. Supported transitions are:

- `pending_payment` to `paid`, `payment_failed`, or `cancelled`
- `payment_failed` to `pending_payment` or `cancelled`
- `paid` to `fulfilled` or `cancelled`

Use the model method to enforce these transitions:

```php
use Larasell\Larasell\Enums\OrderStatus;

$order->transitionTo(OrderStatus::Fulfilled);
```

## Replacing the payment provider

Implement the payment contract and set the provider class in the published
configuration:

```php
use App\Payments\StripePaymentProvider;

'payments' => [
    'provider' => StripePaymentProvider::class,
],
```

The provider must implement
`Larasell\Larasell\Contracts\PaymentProvider` and return a
`Larasell\Larasell\Payments\PaymentResult`. Its `PaymentRequest` contains
both `amount` and `currency`.
