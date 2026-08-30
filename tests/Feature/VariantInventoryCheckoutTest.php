<?php

use InvalidArgumentException;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Inventory\ReleaseExpiredInventoryForOrder;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Models\ProductVariant;
use Larasell\Larasell\Price;

it('deducts and reserves stock for only the selected variant', function () {
    [$cart, $small, $medium] = variantInventoryCart();
    $cart->add($small, 2);

    $order = variantCheckout($cart)->order;
    $orderItem = $order->items->sole();
    $reservation = $order->inventoryReservations->sole();

    expect($small->fresh()->stock)->toBe(3)
        ->and($medium->fresh()->stock)->toBe(5)
        ->and($orderItem->product_variant_id)->toBe($small->id)
        ->and($orderItem->unit_price->amount())->toBe('1500')
        ->and($reservation->product_variant_id)->toBe($small->id)
        ->and($reservation->quantity)->toBe(2);
});

it('revalidates variant stock and availability after the cart was populated', function () {
    [$cart, $variant] = variantInventoryCart();
    $cart->add($variant, 2);
    $variant->update(['stock' => 1]);

    expect(fn () => variantCheckout($cart))
        ->toThrow(InvalidArgumentException::class, 'Cart item quantity exceeds available variant stock.');

    $variant->update(['stock' => 5, 'status' => Visibility::Hidden]);

    expect(fn () => variantCheckout($cart))
        ->toThrow(InvalidArgumentException::class, 'The product variant is unavailable.');
});

it('restores stock to the selected variant when an order is cancelled', function () {
    [$cart, $small, $medium] = variantInventoryCart();
    $cart->add($small, 2);
    $order = variantCheckout($cart)->order;

    $order->cancel();

    expect($small->fresh()->stock)->toBe(5)
        ->and($medium->fresh()->stock)->toBe(5);
});

it('restores stock to the selected variant when a reservation expires', function () {
    config()->set('larasell.payments.methods.cash.inventory_reservation_minutes', 60);
    [$cart, $small, $medium] = variantInventoryCart();
    $cart->add($small, 2);
    $order = variantCheckout($cart)->order;
    $order->inventoryReservations()->update(['expires_at' => now()->subMinute()]);

    app(ReleaseExpiredInventoryForOrder::class)->handle($order->id);

    expect($small->fresh()->stock)->toBe(5)
        ->and($medium->fresh()->stock)->toBe(5);
});

it('includes the selected variant in checkout idempotency fingerprints', function () {
    [$cart, $small, $medium] = variantInventoryCart();
    $cart->add($small);

    variantCheckout($cart, 'variant-checkout');
    $cart->add($medium);

    expect(fn () => variantCheckout($cart, 'variant-checkout'))
        ->toThrow(InvalidArgumentException::class, 'The idempotency key has already been used with different checkout input.');
});

/** @return array{Cart, ProductVariant, ProductVariant} */
function variantInventoryCart(): array
{
    $product = Product::create([
        'slug' => fake()->unique()->slug(),
        'name' => 'Variant inventory product',
        'price' => Price::of(1000),
        'stock' => 20,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $size = ProductAttribute::create([
        'slug' => fake()->unique()->bothify('inventory-size-####'),
        'name' => 'Size',
    ]);
    $small = $size->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'small']);
    $medium = $size->values()->create(['slug' => 'medium', 'name' => 'Medium', 'value' => 'medium']);
    $product->attributeValues()->attach([$small->id, $medium->id]);
    $variants = $product->generateVariants([$size]);
    $variants->each->update([
        'price' => Price::of(1500),
        'stock' => 5,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);

    return [Cart::create(['currency' => Currency::EUR]), $variants->first()->refresh(), $variants->last()->refresh()];
}

function variantCheckout(Cart $cart, ?string $idempotencyKey = null): mixed
{
    return app(Checkout::class)->create($cart, [
        'customer_email' => 'variant-inventory@example.com',
        'customer_name' => 'Variant Inventory Customer',
    ], idempotencyKey: $idempotencyKey);
}
