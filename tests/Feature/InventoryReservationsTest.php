<?php

use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\InventoryReservation;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

/** @return array<string, string> */
function inventoryReservationCheckoutData(): array
{
    return [
        'customer_email' => 'reservations@example.com',
        'customer_name' => 'Reservation Customer',
    ];
}

/** @return array{Cart, Product} */
function inventoryReservationCart(?int $stock = 5, int $quantity = 2): array
{
    $product = Product::query()->create([
        'slug' => 'reservation-product-'.Product::query()->count(),
        'name' => 'Reservation product',
        'price' => Price::of(1000),
        'stock' => $stock,
        'allow_backorders' => $stock === null,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product, $quantity);

    return [$cart, $product];
}

it('creates an active inventory reservation when checkout deducts stock', function () {
    [$cart, $product] = inventoryReservationCart();

    $order = app(Checkout::class)->create($cart, inventoryReservationCheckoutData())->order;
    $item = $order->items->sole();
    $reservation = InventoryReservation::query()->sole();

    expect($reservation->order_id)->toBe($order->id)
        ->and($reservation->order_item_id)->toBe($item->id)
        ->and($reservation->product_id)->toBe($product->id)
        ->and($reservation->quantity)->toBe(2)
        ->and($reservation->status)->toBe(InventoryReservationStatus::Active)
        ->and($reservation->consumed_at)->toBeNull()
        ->and($reservation->released_at)->toBeNull()
        ->and($product->fresh()->stock)->toBe(3);
});

it('does not reserve inventory for products without tracked stock', function () {
    [$cart, $product] = inventoryReservationCart(stock: null);

    app(Checkout::class)->create($cart, inventoryReservationCheckoutData());

    expect(InventoryReservation::query()->count())->toBe(0)
        ->and($product->fresh()->stock)->toBeNull();
});
