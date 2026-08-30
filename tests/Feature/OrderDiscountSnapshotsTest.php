<?php

use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingMethod;

final class OrderSnapshotDiscountPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'order-snapshot-discount',
            name: 'Order snapshot discount',
            allocations: $context->fixedAmountOff(Price::of(500)),
        );
    }
}

final class OrderSnapshotShippingPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'free-order-snapshot-shipping',
            name: 'Free order snapshot shipping',
            allocations: $context->fixedAmountOffShipping(Price::of(300)),
        );
    }
}

final class OrderSnapshotShippingMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('order-snapshot-shipping', 'Order snapshot shipping', Price::of(300), false);
    }
}

it('snapshots applied discounts and their permanent order line allocations', function () {
    app(PromotionManager::class)->register(OrderSnapshotDiscountPromotion::class);
    $cart = orderDiscountSnapshotCart();

    $order = app(Checkout::class)->create($cart, orderDiscountSnapshotData())->order;
    $item = $order->items->sole();

    expect($order->subtotal->amount())->toBe('1000')
        ->and($order->discount_total->amount())->toBe('500')
        ->and($order->total->amount())->toBe('500')
        ->and($order->payments->sole()->amount->amount())->toBe('500')
        ->and($item->discount_total->amount())->toBe('500')
        ->and($item->total->amount())->toBe('1000')
        ->and($order->discounts)->toEqual([[
            'identifier' => 'order-snapshot-discount',
            'name' => 'Order snapshot discount',
            'total' => ['amount' => '500'],
            'allocations' => [[
                'target' => 'line',
                'order_item_id' => $item->getKey(),
                'amount' => ['amount' => '500'],
            ]],
        ]]);
});

it('snapshots shipping discount allocations separately from order lines', function () {
    app(ShippingManager::class)->register(OrderSnapshotShippingMethod::class);
    app(PromotionManager::class)->register(OrderSnapshotShippingPromotion::class);
    $cart = orderDiscountSnapshotCart()->selectShippingOption('order-snapshot-shipping');

    $order = app(Checkout::class)->create($cart, orderDiscountSnapshotData())->order;

    expect($order->shipping_price?->amount())->toBe('300')
        ->and($order->discount_total->amount())->toBe('300')
        ->and($order->total->amount())->toBe('1000')
        ->and($order->items->sole()->discount_total->amount())->toBe('0')
        ->and($order->discounts[0]['allocations'])->toEqual([[
            'target' => 'shipping',
            'order_item_id' => null,
            'amount' => ['amount' => '300'],
        ]]);
});

it('stores zero-value discount snapshots when no promotion applies', function () {
    $order = app(Checkout::class)
        ->create(orderDiscountSnapshotCart(), orderDiscountSnapshotData())
        ->order;

    expect($order->discount_total->amount())->toBe('0')
        ->and($order->discounts)->toBe([])
        ->and($order->items->sole()->discount_total->amount())->toBe('0');
});

function orderDiscountSnapshotCart(): Cart
{
    $product = Product::query()->create([
        'slug' => 'order-discount-snapshot-'.fake()->unique()->uuid(),
        'name' => 'Snapshot product',
        'price' => Price::of(1000),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    return $cart;
}

/** @return array<string, mixed> */
function orderDiscountSnapshotData(): array
{
    return [
        'customer_email' => 'buyer@example.com',
        'customer_name' => 'Buyer',
    ];
}
