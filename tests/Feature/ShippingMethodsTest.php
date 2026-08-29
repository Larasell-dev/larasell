<?php

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Larasell\Larasell\Address;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingMethod;
use Larasell\Larasell\Shipping\ShippingOption;

class TestShippingMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('standard', 'Standard shipping', Price::of(500));

        if ($cart->quantity() >= 2) {
            $this->register('express', 'Express shipping', Price::of(1200));
        }
    }
}

class TestPickupMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('pickup', 'Pickup', Price::of(0), requiresAddress: false);
    }
}

beforeEach(function () {
    app(ShippingManager::class)->register(TestShippingMethod::class);
});

it('gets multiple shipping options with access to the cart', function () {
    $cart = Cart::create(['currency' => Currency::EUR]);
    $cart->add(shippingProduct(), 2);

    $options = $cart->shippingOptions();

    expect($options)->toBeInstanceOf(Collection::class)
        ->and($options)->toHaveCount(2)
        ->and($options->first())->toBeInstanceOf(ShippingOption::class)
        ->and($options->pluck('handle')->all())->toBe(['standard', 'express'])
        ->and($options->first()->price->amount())->toBe('500');
});

it('exposes whether a shipping option requires an address', function () {
    $cart = Cart::create(['currency' => Currency::EUR]);
    app(ShippingManager::class)->register(TestPickupMethod::class);

    $options = $cart->shippingOptions()->keyBy('handle');

    expect($options['standard']->requiresAddress)->toBeTrue()
        ->and($options['standard']->toArray()['requires_address'])->toBeTrue()
        ->and($options['pickup']->requiresAddress)->toBeFalse()
        ->and($options['pickup']->toArray()['requires_address'])->toBeFalse();
});

it('requires a shipping address when the selected option requires one', function () {
    $cart = Cart::create(['currency' => Currency::EUR]);
    $cart->add(shippingProduct());
    $cart->selectShippingOption('standard');
    $data = shippingCheckoutData();
    unset($data['shipping_address']);

    expect(fn () => app(Checkout::class)->create($cart, $data))
        ->toThrow(InvalidArgumentException::class, 'A shipping_address is required for the selected shipping option.');
});

it('checks out without addresses when the selected option does not require one', function () {
    app(ShippingManager::class)->register(TestPickupMethod::class);
    $cart = Cart::create(['currency' => Currency::EUR]);
    $cart->add(shippingProduct());
    $cart->selectShippingOption('pickup');

    $order = app(Checkout::class)->create($cart, [
        'customer_email' => 'buyer@example.com',
        'customer_name' => 'Buyer Name',
    ])->order;

    expect($order->billing_address)->toBeNull()
        ->and($order->shipping_address)->toBeNull();
});

it('persists a selected shipping option and includes it in the cart total', function () {
    $cart = Cart::create(['currency' => Currency::EUR]);
    $cart->add(shippingProduct(['price' => Price::of(1000)]), 2);

    $cart->selectShippingOption('express');
    $freshCart = $cart->fresh();

    expect($freshCart->shipping_option)->toBe('express')
        ->and($freshCart->shippingOption()?->name)->toBe('Express shipping')
        ->and($freshCart->subtotal()?->amount())->toBe('2000')
        ->and($freshCart->total()?->amount())->toBe('3200');
});

it('rejects unavailable shipping options', function () {
    $cart = Cart::create(['currency' => Currency::EUR]);
    $cart->add(shippingProduct());

    expect(fn () => $cart->selectShippingOption('express'))
        ->toThrow(InvalidArgumentException::class, 'Shipping option [express] is not available for this cart.');
});

it('rejects a selected option that becomes unavailable', function () {
    $cart = Cart::create(['currency' => Currency::EUR]);
    $firstProduct = shippingProduct();
    $secondProduct = shippingProduct();
    $cart->add($firstProduct);
    $cart->add($secondProduct);
    $cart->selectShippingOption('express');
    $cart->remove($secondProduct);

    expect(fn () => $cart->total())
        ->toThrow(InvalidArgumentException::class, 'Selected shipping option [express] is no longer available for this cart.');
});

it('snapshots selected shipping details and charges them at checkout', function () {
    $cart = Cart::create(['currency' => Currency::EUR]);
    $cart->add(shippingProduct(['price' => Price::of(1000)]));
    $cart->selectShippingOption('standard');

    $order = app(Checkout::class)->create($cart, shippingCheckoutData())->order;

    expect($order->subtotal->amount())->toBe('1000')
        ->and($order->shipping_method)->toBe(TestShippingMethod::class)
        ->and($order->shipping_option)->toBe('standard')
        ->and($order->shipping_option_name)->toBe('Standard shipping')
        ->and($order->shipping_price?->amount())->toBe('500')
        ->and($order->total->amount())->toBe('1500')
        ->and($order->payments->first()->amount->amount())->toBe('1500');
});

/** @param array<string, mixed> $attributes */
function shippingProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(3, true),
        'price' => Price::of(1000),
        'status' => Visibility::Visible,
    ], $attributes));
}

/** @return array<string, mixed> */
function shippingCheckoutData(): array
{
    $address = new Address(
        country: 'DE',
        firstName: 'Buyer',
        lastName: 'Name',
        street: 'Main Street 1',
        city: 'Berlin',
        postcode: '10115',
        email: 'buyer@example.com',
    );

    return [
        'customer_email' => 'buyer@example.com',
        'customer_name' => 'Buyer Name',
        'billing_address' => $address,
        'shipping_address' => $address,
    ];
}
