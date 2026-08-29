<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Events\InventoryDecremented;
use Larasell\Larasell\Events\InventoryReservationConsumed;
use Larasell\Larasell\Events\InventoryReservationExpired;
use Larasell\Larasell\Events\InventoryReservationReleased;
use Larasell\Larasell\Events\InventoryReserved;
use Larasell\Larasell\Events\InventoryRestocked;
use Larasell\Larasell\Inventory\ReleaseExpiredInventoryForOrder;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

/** @return array{Order, Product} */
function inventoryEventOrder(?int $stock = 5, int $quantity = 2): array
{
    $product = Product::query()->create([
        'slug' => 'inventory-event-'.str()->random(8),
        'name' => 'Inventory event product',
        'price' => Price::of(1000),
        'stock' => $stock,
        'allow_backorders' => $stock === null,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product, $quantity);

    $order = app(Checkout::class)->create($cart, [
        'customer_email' => 'inventory-events@example.com',
        'customer_name' => 'Inventory Events Customer',
    ])->order;

    return [$order, $product];
}

it('dispatches an event when checkout decrements inventory', function () {
    Event::fake([InventoryDecremented::class, InventoryReserved::class]);

    [$order, $product] = inventoryEventOrder();

    Event::assertDispatched(InventoryDecremented::class, fn (InventoryDecremented $event): bool => $event->order->is($order)
        && $event->product->is($product)
        && $event->quantity === 2
    );
    Event::assertDispatched(InventoryReserved::class, fn (InventoryReserved $event): bool => $event->reservation->order_id === $order->id
        && $event->reservation->product_id === $product->id
        && $event->reservation->quantity === 2
        && $event->reservation->status === InventoryReservationStatus::Active
    );
});

it('dispatches a consumed event once when reserved inventory is paid', function () {
    Event::fake([InventoryReservationConsumed::class]);
    [$order] = inventoryEventOrder();

    $payment = $order->payments->sole();
    $payment->markAsPaid();
    $payment->markAsPaid();

    Event::assertDispatchedTimes(InventoryReservationConsumed::class, 1);
    Event::assertDispatched(
        InventoryReservationConsumed::class,
        fn (InventoryReservationConsumed $event): bool => $event->reservation->status === InventoryReservationStatus::Consumed
            && $event->reservation->consumed_at !== null
    );
});

it('dispatches restocked and released events once when an order is cancelled', function () {
    Event::fake([InventoryRestocked::class, InventoryReservationReleased::class]);
    [$order, $product] = inventoryEventOrder();

    $order->cancel();
    $order->cancel();

    Event::assertDispatchedTimes(InventoryRestocked::class, 1);
    Event::assertDispatched(InventoryRestocked::class, fn (InventoryRestocked $event): bool => $event->order->is($order)
        && $event->product->is($product)
        && $event->quantity === 2
    );
    Event::assertDispatchedTimes(InventoryReservationReleased::class, 1);
    Event::assertDispatched(
        InventoryReservationReleased::class,
        fn (InventoryReservationReleased $event): bool => $event->reservation->status === InventoryReservationStatus::Released
            && $event->reservation->release_reason === 'order_cancelled'
    );
});

it('dispatches released and expired events when a reservation expires', function () {
    Event::fake([InventoryReservationReleased::class, InventoryReservationExpired::class]);
    [$order] = inventoryEventOrder();
    $order->inventoryReservations()->update(['expires_at' => now()->subMinute()]);

    app(ReleaseExpiredInventoryForOrder::class)->handle($order->id);

    Event::assertDispatchedTimes(InventoryReservationReleased::class, 1);
    Event::assertDispatchedTimes(InventoryReservationExpired::class, 1);
    Event::assertDispatched(
        InventoryReservationExpired::class,
        fn (InventoryReservationExpired $event): bool => $event->reservation->release_reason === 'reservation_expired'
    );
});

it('does not dispatch inventory events when inventory is unchanged', function () {
    Event::fake([InventoryDecremented::class, InventoryRestocked::class]);

    [$untrackedOrder] = inventoryEventOrder(stock: null);
    [$trackedOrder] = inventoryEventOrder();
    $untrackedOrder->cancel();
    $trackedOrder->cancel(restock: false);

    Event::assertNotDispatched(InventoryDecremented::class, fn (InventoryDecremented $event): bool => $event->order->is($untrackedOrder)
    );
    Event::assertNotDispatched(InventoryRestocked::class);
});
