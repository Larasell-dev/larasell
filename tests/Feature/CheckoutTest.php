<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Larasell\Larasell\Address;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\PaymentProvider;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Payments\FakePaymentProvider;
use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;
use Larasell\Larasell\Price;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function checkoutData(?int $customerId = null): array
{
    return [
        'customer_email' => 'buyer@example.com',
        'customer_name' => 'Buyer Name',
        'billing_address' => new Address(
            country: 'DE',
            firstName: 'Buyer',
            lastName: 'Name',
            street: 'Main Street 1',
            city: 'Berlin',
            postcode: '10115',
            email: 'buyer@example.com',
        ),
        'shipping_address' => [
            'country' => 'DE',
            'first_name' => 'Buyer',
            'last_name' => 'Name',
            'street' => ['Shipping Street 2', 'Second floor'],
            'city' => 'Berlin',
            'postcode' => '10117',
            'phone' => '+49 30 123456',
        ],
        'customer_id' => $customerId,
    ];
}

it('checks out a guest cart and records a successful fake payment', function () {
    $product = Product::query()->create([
        'slug' => 'coffee',
        'name' => 'Coffee',
        'price' => Price::of(1299),
        'stock' => 5,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR, 'session_id' => 'guest-session']);
    $cart->add($product, 2);

    $order = app(Checkout::class)->create($cart, checkoutData());

    expect($order->number)->toBe('LS-000001')
        ->and($order->currency)->toBe(Currency::EUR)
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->customer_id)->toBeNull()
        ->and($order->customer_email)->toBe('buyer@example.com')
        ->and($order->total->amount())->toBe('2598')
        ->and($order->total->toArray())->toBe(['amount' => '2598'])
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_name)->toBe('Coffee')
        ->and($order->payments->first()->status)->toBe(PaymentStatus::Succeeded)
        ->and($cart->fresh()->items)->toHaveCount(0)
        ->and($product->fresh()->stock)->toBe(3);
});

it('keeps snapshots after the source product changes', function () {
    $product = Product::query()->create([
        'slug' => 'tea',
        'name' => 'Tea',
        'price' => Price::of(500),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR, 'user_id' => 42]);
    $cart->add($product);

    $order = app(Checkout::class)->create($cart, checkoutData(42));
    $product->update(['name' => 'Changed Tea', 'price' => Price::of(900)]);
    $product->delete();

    $item = $order->items()->first();
    expect($order->customer_id)->toBe(42)
        ->and($order->billing_address)->toBeInstanceOf(Address::class)
        ->and($order->billing_address->city)->toBe('Berlin')
        ->and($order->billing_address->street)->toBe(['Main Street 1'])
        ->and($order->shipping_address->street)->toBe(['Shipping Street 2', 'Second floor'])
        ->and($item->product_name)->toBe('Tea')
        ->and($item->unit_price->amount())->toBe('500');
});

it('records declined payments and marks the order as failed', function () {
    config()->set('larasell.payments.fake.succeeds', false);
    $product = Product::query()->create([
        'slug' => 'mug',
        'name' => 'Mug',
        'price' => Price::of(1500),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    $order = app(Checkout::class)->create($cart, checkoutData());

    expect($order->status)->toBe(OrderStatus::PaymentFailed)
        ->and($order->payments->first()->status)->toBe(PaymentStatus::Failed)
        ->and($order->payments->first()->failure_message)->toBe('The fake payment was declined.');
});

it('keeps an order pending when payment is deferred', function () {
    app()->bind(PaymentProvider::class, fn () => new class implements PaymentProvider
    {
        public function pay(PaymentRequest $request): PaymentResult
        {
            return PaymentResult::pending();
        }
    });

    $product = Product::query()->create([
        'slug' => 'cash-coffee',
        'name' => 'Cash coffee',
        'price' => Price::of(500),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    $order = app(Checkout::class)->create($cart, checkoutData());

    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->payments->first()->status)->toBe(PaymentStatus::Pending);
});

it('prevents fake payments outside local and testing environments', function () {
    app()->detectEnvironment(fn () => 'production');

    $provider = app(FakePaymentProvider::class);

    expect(fn () => $provider->pay(new PaymentRequest(
        orderNumber: 'LS-000001',
        amount: Price::of(1000),
        currency: Currency::EUR,
        customerEmail: 'buyer@example.com',
    )))->toThrow(LogicException::class, 'The fake payment provider may only run in local or testing environments.');
});

it('rejects invalid order status transitions', function () {
    $product = Product::query()->create([
        'slug' => 'plate',
        'name' => 'Plate',
        'price' => Price::of(1000),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);
    $order = app(Checkout::class)->create($cart, checkoutData());

    $order->transitionTo(OrderStatus::Fulfilled);

    expect(fn () => $order->transitionTo(OrderStatus::Paid))
        ->toThrow(InvalidArgumentException::class);
});
