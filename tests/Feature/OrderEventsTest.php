<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Events\OrderCancelled;
use Larasell\Larasell\Events\OrderFulfilled;
use Larasell\Larasell\Events\OrderPaid;
use Larasell\Larasell\Events\OrderPlaced;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

/** @return array<string, string> */
function orderEventData(): array
{
    return [
        'customer_email' => 'order-events@example.com',
        'customer_name' => 'Order Events Customer',
    ];
}

function orderEventCart(string $slug): Cart
{
    $product = Product::query()->create([
        'slug' => $slug,
        'name' => 'Order event product',
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
function orderEvents(): array
{
    return [
        OrderPlaced::class,
        OrderPaid::class,
        OrderFulfilled::class,
        OrderCancelled::class,
    ];
}

it('dispatches events through the placed, paid, and fulfilled order lifecycle', function () {
    Event::fake(orderEvents());

    $order = app(Checkout::class)->create(orderEventCart('successful-order-events'), orderEventData())->order;

    Event::assertDispatched(OrderPlaced::class, fn ($event) => $event->order->is($order));

    $order->payments->first()->markAsPaid();

    Event::assertDispatchedTimes(OrderPaid::class, 1);

    $order->refresh();
    $order->transitionTo(OrderStatus::Fulfilled);

    Event::assertDispatchedTimes(OrderFulfilled::class, 1);
});

it('dispatches the order cancelled event once', function () {
    Event::fake(orderEvents());

    $order = app(Checkout::class)->create(orderEventCart('cancelled-order-events'), orderEventData())->order;
    $order->cancel();
    $order->cancel();

    Event::assertDispatchedTimes(OrderCancelled::class, 1);
});
