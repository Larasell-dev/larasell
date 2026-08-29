<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Events\InventoryDecremented;
use Larasell\Larasell\Events\InventoryRestocked;
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
    Event::fake([InventoryDecremented::class]);

    [$order, $product] = inventoryEventOrder();

    Event::assertDispatched(InventoryDecremented::class, fn (InventoryDecremented $event): bool => $event->order->is($order)
        && $event->product->is($product)
        && $event->quantity === 2
    );
});

it('dispatches a restock event once when a cancelled order restores inventory', function () {
    Event::fake([InventoryRestocked::class]);
    [$order, $product] = inventoryEventOrder();

    $order->cancel();
    $order->cancel();

    Event::assertDispatchedTimes(InventoryRestocked::class, 1);
    Event::assertDispatched(InventoryRestocked::class, fn (InventoryRestocked $event): bool => $event->order->is($order)
        && $event->product->is($product)
        && $event->quantity === 2
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
