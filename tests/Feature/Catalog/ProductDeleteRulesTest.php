<?php

use Larasell\Larasell\Catalog\Actions\DeleteProduct;
use Larasell\Larasell\Catalog\Exceptions\ProductInUse;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\InventoryReservation;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Price;

/** @return array{Order, Product} */
function orderedProduct(): array
{
    $product = Product::query()->create([
        'name' => ['en' => 'Boots'],
        'price' => Price::of(1000),
        'stock' => 5,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product, 2);
    $order = app(Checkout::class)->create($cart, ['customer_email' => 'a@example.com', 'customer_name' => 'A'])->order;

    return [$order, $product];
}

it('refuses to delete a product of an open order', function () {
    [$order, $product] = orderedProduct();
    // An active reservation would refuse the delete too; this one checks the order rule on its own.
    InventoryReservation::query()->update(['status' => InventoryReservationStatus::Released]);

    expect(fn () => app(DeleteProduct::class)->handle($product->id))
        ->toThrow(ProductInUse::class, "open order [{$order->id}]")
        ->and(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('refuses to delete a product with reserved stock', function () {
    [$order, $product] = orderedProduct();
    Order::query()->whereKey($order->id)->update(['status' => OrderStatus::Fulfilled]);

    expect(fn () => app(DeleteProduct::class)->handle($product->id))
        ->toThrow(ProductInUse::class, '2 reserved')
        ->and(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('deletes a product whose orders are all closed', function () {
    [$order, $product] = orderedProduct();
    $order->cancel();

    app(DeleteProduct::class)->handle($product->id);

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
});
