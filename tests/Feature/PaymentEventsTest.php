<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\PaymentProvider;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Events\PaymentCancelled;
use Larasell\Larasell\Events\PaymentFailed;
use Larasell\Larasell\Events\PaymentPending;
use Larasell\Larasell\Events\PaymentSucceeded;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;
use Larasell\Larasell\Price;

class FailedPaymentEventProvider implements PaymentProvider
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        return new PaymentResult(false, failureMessage: 'Failed.');
    }
}

/** @return array<string, string> */
function paymentEventData(): array
{
    return [
        'customer_email' => 'payment-events@example.com',
        'customer_name' => 'Payment Events Customer',
    ];
}

function paymentEventCart(string $slug): Cart
{
    $product = Product::query()->create([
        'slug' => $slug,
        'name' => 'Payment event product',
        'price' => Price::of(1000),
        'stock' => 5,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    return $cart;
}

/** @return array<class-string> */
function paymentEvents(): array
{
    return [
        PaymentPending::class,
        PaymentSucceeded::class,
        PaymentFailed::class,
        PaymentCancelled::class,
    ];
}

it('dispatches pending and succeeded payment events once', function () {
    Event::fake(paymentEvents());

    $order = app(Checkout::class)->create(paymentEventCart('successful-payment-events'), paymentEventData());

    Event::assertDispatched(PaymentPending::class, fn ($event) => $event->payment->order_id === $order->id);

    $payment = $order->payments->first();
    $payment->markAsPaid();
    $payment->markAsPaid();

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
});

it('dispatches the payment cancelled event once', function () {
    Event::fake(paymentEvents());

    $order = app(Checkout::class)->create(paymentEventCart('cancelled-payment-events'), paymentEventData());
    $order->cancel();
    $order->cancel();

    Event::assertDispatchedTimes(PaymentCancelled::class, 1);
});

it('dispatches an event when a payment fails', function () {
    Event::fake(paymentEvents());
    config()->set('larasell.payments.methods.failed_events', [
        'driver' => 'failed_events',
        'provider' => FailedPaymentEventProvider::class,
    ]);

    $order = app(Checkout::class)->create(
        paymentEventCart('failed-payment-events'),
        paymentEventData(),
        'failed_events',
    );

    Event::assertDispatched(PaymentFailed::class, fn ($event) => $event->payment->order_id === $order->id);
});
