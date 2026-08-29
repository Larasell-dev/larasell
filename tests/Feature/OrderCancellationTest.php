<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

uses(RefreshDatabase::class);

/** @return array<string, string> */
function cancellationCheckoutData(): array
{
    return [
        'customer_email' => 'cancel@example.com',
        'customer_name' => 'Cancel Customer',
    ];
}

function cancellableOrder(int $stock = 5, int $quantity = 2): array
{
    $product = Product::query()->create([
        'slug' => 'cancel-product',
        'name' => 'Cancel product',
        'price' => Price::of(1000),
        'stock' => $stock,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product, $quantity);

    $order = app(Checkout::class)->create($cart, cancellationCheckoutData())->order;

    return [$order, $product];
}

it('cancels an unpaid order, its pending payment, and restocks inventory', function () {
    [$order, $product] = cancellableOrder();

    $cancelled = $order->cancel();

    expect($cancelled->status)->toBe(OrderStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->cancellation_reason)->toBeNull()
        ->and($cancelled->inventory_restocked_at)->not->toBeNull()
        ->and($cancelled->payments->first()->status)->toBe(PaymentStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(5);
});

it('records an optional cancellation reason', function () {
    [$order] = cancellableOrder();

    $cancelled = $order->cancel(reason: 'Customer requested cancellation');

    expect($cancelled->cancellation_reason)->toBe('Customer requested cancellation');
});

it('does not replace the cancellation reason when cancellation is repeated', function () {
    [$order] = cancellableOrder();

    $order->cancel(reason: 'Customer requested cancellation');
    $cancelled = $order->cancel(reason: 'Inventory expired');

    expect($cancelled->cancellation_reason)->toBe('Customer requested cancellation');
});

it('cancels idempotently without restocking inventory twice', function () {
    [$order, $product] = cancellableOrder();

    $first = $order->cancel();
    $second = $order->cancel();

    expect($product->fresh()->stock)->toBe(5)
        ->and($second->cancelled_at->toISOString())->toBe($first->cancelled_at->toISOString())
        ->and($second->inventory_restocked_at->toISOString())->toBe($first->inventory_restocked_at->toISOString());
});

it('can cancel without restocking inventory', function () {
    [$order, $product] = cancellableOrder();

    $cancelled = $order->cancel(restock: false);

    expect($cancelled->status)->toBe(OrderStatus::Cancelled)
        ->and($cancelled->inventory_restocked_at)->toBeNull()
        ->and($product->fresh()->stock)->toBe(3);
});

it('does not cancel an order that has been paid', function () {
    [$order, $product] = cancellableOrder();
    $order->payments->first()->markAsPaid();

    expect(fn () => $order->cancel())
        ->toThrow(InvalidArgumentException::class, 'An order with a successful payment cannot be cancelled before it is refunded.');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($product->fresh()->stock)->toBe(3);
});

it('does not cancel or restock when a successful payment exists', function () {
    [$order, $product] = cancellableOrder();
    $order->payments->first()->update(['status' => PaymentStatus::Succeeded]);

    expect(fn () => $order->cancel())
        ->toThrow(InvalidArgumentException::class, 'An order with a successful payment cannot be cancelled before it is refunded.');

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($product->fresh()->stock)->toBe(3);
});
