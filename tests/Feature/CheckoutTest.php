<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Address;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\PaymentProvider;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;
use Larasell\Larasell\Payments\RedirectPaymentAction;
use Larasell\Larasell\Price;

uses(RefreshDatabase::class);

class DecliningCheckoutPaymentProvider implements PaymentProvider
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        return PaymentResult::failed('The payment was declined.');
    }
}

class RedirectingCheckoutPaymentProvider implements PaymentProvider
{
    public static ?PaymentRequest $request = null;

    public function initiate(PaymentRequest $request): PaymentResult
    {
        self::$request = $request;

        return PaymentResult::pending(
            reference: 'checkout-session-123',
            action: new RedirectPaymentAction('https://payments.example.com/session/123'),
        );
    }
}

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

it('checks out a guest cart with the default cash payment method', function () {
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

    $result = app(Checkout::class)->create($cart, checkoutData());
    $order = $result->order;

    expect($order->number)->toBe('LS-000001')
        ->and($order->currency)->toBe(Currency::EUR)
        ->and($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->customer_id)->toBeNull()
        ->and($order->customer_email)->toBe('buyer@example.com')
        ->and($order->total->amount())->toBe('2598')
        ->and($order->total->toArray())->toBe(['amount' => '2598'])
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_name)->toBe('Coffee')
        ->and($order->items->first()->inventory_quantity)->toBe(2)
        ->and($order->payments->first()->method)->toBe('cash')
        ->and($order->payments->first()->provider)->toBe('offline')
        ->and($order->payments->first()->status)->toBe(PaymentStatus::Pending)
        ->and($result->payment->is($order->payments->first()))->toBeTrue()
        ->and($result->action)->toBeNull()
        ->and($result->requiresRedirect())->toBeFalse()
        ->and($cart->fresh()->items)->toHaveCount(0)
        ->and($product->fresh()->stock)->toBe(3);
});

it('locks products in a deterministic order', function () {
    $firstProduct = Product::query()->create([
        'slug' => 'first-product',
        'name' => 'First product',
        'price' => Price::of(500),
        'stock' => 5,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $secondProduct = Product::query()->create([
        'slug' => 'second-product',
        'name' => 'Second product',
        'price' => Price::of(700),
        'stock' => 5,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($secondProduct);
    $cart->add($firstProduct);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    app(Checkout::class)->create($cart, checkoutData());

    expect(collect($queries)->contains(fn (string $query): bool => str_contains($query, 'from "larasell_cart_items"')
        && str_contains($query, 'order by "product_id" asc')
    ))->toBeTrue();
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

    $order = app(Checkout::class)->create($cart, checkoutData(42))->order;
    $product->update(['name' => 'Changed Tea', 'price' => Price::of(900)]);
    $product->delete();

    $item = $order->items()->first();
    expect($order->customer_id)->toBe(42)
        ->and($order->billing_address)->toBeInstanceOf(Address::class)
        ->and($order->billing_address->city)->toBe('Berlin')
        ->and($order->billing_address->street)->toBe(['Main Street 1'])
        ->and($order->shipping_address->street)->toBe(['Shipping Street 2', 'Second floor'])
        ->and($item->product_name)->toBe('Tea')
        ->and($item->inventory_quantity)->toBe(0)
        ->and($item->unit_price->amount())->toBe('500');
});

it('snapshots company and tax identifiers from address payloads', function () {
    $product = Product::query()->create([
        'slug' => 'business-order',
        'name' => 'Business order',
        'price' => Price::of(1200),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);
    $data = checkoutData();
    $data['billing_address'] = [
        'country' => 'DE',
        'first_name' => 'Buyer',
        'last_name' => 'Name',
        'company' => 'Example GmbH',
        'tax_id' => 'DE123456789',
        'street' => 'Main Street 1',
        'city' => 'Berlin',
        'postcode' => '10115',
    ];

    $order = app(Checkout::class)->create($cart, $data)->order;
    $data['billing_address']['company'] = 'Changed Company';
    $data['billing_address']['tax_id'] = 'CHANGED';
    $address = $order->fresh()->billing_address;

    expect($address?->company)->toBe('Example GmbH')
        ->and($address?->taxId)->toBe('DE123456789');
});

it('records declined payments and marks the order as failed', function () {
    config()->set('larasell.payments.methods.declining', [
        'driver' => 'declining',
        'provider' => DecliningCheckoutPaymentProvider::class,
    ]);
    $product = Product::query()->create([
        'slug' => 'mug',
        'name' => 'Mug',
        'price' => Price::of(1500),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    $order = app(Checkout::class)->create($cart, checkoutData(), 'declining')->order;

    expect($order->status)->toBe(OrderStatus::PaymentFailed)
        ->and($order->payments->first()->status)->toBe(PaymentStatus::Failed)
        ->and($order->payments->first()->failure_message)->toBe('The payment was declined.');
});

it('supports the bank transfer payment method', function () {
    $product = Product::query()->create([
        'slug' => 'cash-coffee',
        'name' => 'Cash coffee',
        'price' => Price::of(500),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    $order = app(Checkout::class)->create($cart, checkoutData(), 'bank_transfer')->order;

    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->payments->first()->method)->toBe('bank_transfer')
        ->and($order->payments->first()->provider)->toBe('offline')
        ->and($order->payments->first()->status)->toBe(PaymentStatus::Pending);
});

it('passes persisted models and options to a redirecting payment provider', function () {
    config()->set('larasell.payments.methods.redirecting', [
        'driver' => 'redirecting',
        'provider' => RedirectingCheckoutPaymentProvider::class,
    ]);
    $product = Product::query()->create([
        'slug' => 'redirect-product',
        'name' => 'Redirect product',
        'price' => Price::of(2000),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    $result = app(Checkout::class)->create(
        $cart,
        checkoutData(),
        'redirecting',
        ['success_url' => 'https://shop.example.com/success'],
    );

    expect(RedirectingCheckoutPaymentProvider::$request?->order->exists)->toBeTrue()
        ->and(RedirectingCheckoutPaymentProvider::$request?->payment->exists)->toBeTrue()
        ->and(RedirectingCheckoutPaymentProvider::$request?->payment->order_id)->toBe($result->order->id)
        ->and(RedirectingCheckoutPaymentProvider::$request?->option('success_url'))->toBe('https://shop.example.com/success')
        ->and($result->payment->reference)->toBe('checkout-session-123')
        ->and(Payment::findByProviderReference('redirecting', 'checkout-session-123')->is($result->payment))->toBeTrue()
        ->and($result->action)->toBeInstanceOf(RedirectPaymentAction::class)
        ->and($result->requiresRedirect())->toBeTrue()
        ->and($result->redirect()->getTargetUrl())->toBe('https://payments.example.com/session/123');
});

it('rejects an unknown payment method before modifying the cart', function () {
    $product = Product::query()->create([
        'slug' => 'unknown-method',
        'name' => 'Unknown method product',
        'price' => Price::of(500),
        'stock' => 2,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    expect(fn () => app(Checkout::class)->create($cart, checkoutData(), 'unknown'))
        ->toThrow(InvalidArgumentException::class, 'Payment method [unknown] is not configured.');

    expect($cart->fresh()->items)->toHaveCount(1)
        ->and($product->fresh()->stock)->toBe(2)
        ->and(Order::query()->count())->toBe(0);
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
    $order = app(Checkout::class)->create($cart, checkoutData())->order;

    $order->payments->first()->markAsPaid();
    $order->refresh();
    $order->transitionTo(OrderStatus::Fulfilled);

    expect(fn () => $order->transitionTo(OrderStatus::Paid))
        ->toThrow(InvalidArgumentException::class);
});
