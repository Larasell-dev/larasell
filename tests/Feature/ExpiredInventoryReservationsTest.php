<?php

use Illuminate\Support\Carbon;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Inventory\ReleaseExpiredInventoryForOrder;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

/** @return array{Order, Product} */
function orderWithExpiringInventory(?Carbon $expiresAt): array
{
    config()->set('larasell.payments.methods.cash.inventory_reservation_minutes', 60);

    $product = Product::query()->create([
        'slug' => 'expiring-reservation-'.Product::query()->count(),
        'name' => 'Expiring reservation product',
        'price' => Price::of(1000),
        'stock' => 5,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product, 2);

    $order = app(Checkout::class)->create($cart, [
        'customer_email' => 'expired@example.com',
        'customer_name' => 'Expired Reservation Customer',
    ])->order;
    $order->inventoryReservations()->update(['expires_at' => $expiresAt]);

    return [$order, $product];
}

it('releases expired inventory and cancels its unpaid order', function () {
    $this->travelTo('2026-08-29 12:00:00');
    [$order, $product] = orderWithExpiringInventory(now()->subMinute());

    $released = app(ReleaseExpiredInventoryForOrder::class)->handle($order->id);

    $order->refresh();
    $reservation = $order->inventoryReservations->sole();

    expect($released)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancellation_reason)->toBe('Inventory reservation expired')
        ->and($order->payments->sole()->status)->toBe(PaymentStatus::Cancelled)
        ->and($reservation->status)->toBe(InventoryReservationStatus::Released)
        ->and($reservation->release_reason)->toBe('reservation_expired')
        ->and($reservation->released_at?->toDateTimeString())->toBe('2026-08-29 12:00:00')
        ->and($product->fresh()->stock)->toBe(5);
});

it('does not release inventory before its expiration', function (?Carbon $expiresAt) {
    $this->travelTo('2026-08-29 12:00:00');
    [$order, $product] = orderWithExpiringInventory($expiresAt);

    $released = app(ReleaseExpiredInventoryForOrder::class)->handle($order->id);

    expect($released)->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->inventoryReservations()->sole()->status)->toBe(InventoryReservationStatus::Active)
        ->and($product->fresh()->stock)->toBe(3);
})->with([
    'future expiration' => fn () => now()->addMinute(),
    'no expiration' => null,
]);

it('does not release inventory after payment succeeds', function () {
    $this->travelTo('2026-08-29 12:00:00');
    [$order, $product] = orderWithExpiringInventory(now()->subMinute());
    $order->payments->sole()->markAsPaid();

    $released = app(ReleaseExpiredInventoryForOrder::class)->handle($order->id);

    expect($released)->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->inventoryReservations()->sole()->status)->toBe(InventoryReservationStatus::Consumed)
        ->and($product->fresh()->stock)->toBe(3);
});

it('releases expired inventory idempotently', function () {
    [$order, $product] = orderWithExpiringInventory(now()->subMinute());

    $first = app(ReleaseExpiredInventoryForOrder::class)->handle($order->id);
    $second = app(ReleaseExpiredInventoryForOrder::class)->handle($order->id);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($product->fresh()->stock)->toBe(5);
});
