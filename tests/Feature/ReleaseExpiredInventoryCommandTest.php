<?php

use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

function commandReservationOrder(string $slug, string $expiresAt): Order
{
    $product = Product::query()->create([
        'slug' => $slug,
        'name' => 'Command reservation product',
        'price' => Price::of(1000),
        'stock' => 5,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    $order = app(Checkout::class)->create($cart, [
        'customer_email' => $slug.'@example.com',
        'customer_name' => 'Command Reservation Customer',
    ])->order;
    $order->inventoryReservations()->update(['expires_at' => $expiresAt]);

    return $order;
}

it('releases expired inventory reservations in batches', function () {
    $this->travelTo('2026-08-29 12:00:00');
    $expired = [
        commandReservationOrder('expired-command-1', '2026-08-29 11:59:00'),
        commandReservationOrder('expired-command-2', '2026-08-29 12:00:00'),
    ];
    $future = commandReservationOrder('future-command', '2026-08-29 12:01:00');

    $this->artisan('larasell:release-expired-inventory', ['--batch-size' => 1])
        ->expectsOutput('Released inventory for 2 expired orders.')
        ->assertSuccessful();

    expect($expired[0]->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($expired[1]->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($future->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('rejects invalid expiration cleanup batch sizes', function () {
    $this->artisan('larasell:release-expired-inventory', ['--batch-size' => 0])
        ->expectsOutput('The batch size must be a positive integer.')
        ->assertFailed();
});
