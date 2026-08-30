<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\Promotions\HasCode;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Events\PromotionApplied;
use Larasell\Larasell\Events\PromotionCodeApplied;
use Larasell\Larasell\Events\PromotionCodeRemoved;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

final class PromotionEventDiscount implements HasCode, Promotion
{
    public function code(): string
    {
        return 'EVENT10';
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'promotion-event-discount',
            name: 'Promotion event discount',
            allocations: $context->fixedAmountOff(Price::of(100)),
        );
    }
}

beforeEach(function () {
    app(PromotionManager::class)->register(PromotionEventDiscount::class);
});

it('dispatches events when a promotion code is applied and removed', function () {
    Event::fake([PromotionCodeApplied::class, PromotionCodeRemoved::class]);
    $cart = promotionEventCart();

    $cart->applyPromotionCode(' event10 ');
    $cart->applyPromotionCode('EVENT10');

    Event::assertDispatchedTimes(PromotionCodeApplied::class, 1);
    Event::assertDispatched(PromotionCodeApplied::class, fn (PromotionCodeApplied $event): bool => $event->cart->is($cart)
        && $event->code === 'EVENT10'
    );

    $cart->removePromotionCode(' event10 ');
    $cart->removePromotionCode('EVENT10');

    Event::assertDispatchedTimes(PromotionCodeRemoved::class, 1);
    Event::assertDispatched(PromotionCodeRemoved::class, fn (PromotionCodeRemoved $event): bool => $event->cart->is($cart)
        && $event->code === 'EVENT10'
    );
});

it('dispatches an event for each promotion snapshotted during checkout', function () {
    Event::fake([PromotionApplied::class]);
    $cart = promotionEventCart()->applyPromotionCode('EVENT10');

    $order = app(Checkout::class)->create($cart, [
        'customer_email' => 'promotion-events@example.com',
        'customer_name' => 'Promotion Event Customer',
    ])->order;

    Event::assertDispatchedTimes(PromotionApplied::class, 1);
    Event::assertDispatched(PromotionApplied::class, fn (PromotionApplied $event): bool => $event->order->is($order)
        && $event->discount['identifier'] === 'promotion-event-discount'
        && $event->discount['code'] === 'EVENT10'
        && $event->discount['total']['amount'] === '100'
    );
});

function promotionEventCart(): Cart
{
    $product = Product::query()->create([
        'slug' => 'promotion-event-'.fake()->unique()->uuid(),
        'name' => 'Promotion event product',
        'price' => Price::of(1000),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    return $cart;
}
