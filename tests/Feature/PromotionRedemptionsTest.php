<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\PromotionRedemption;
use Larasell\Larasell\Price;

function promotionRedemptionOrder(): Order
{
    $product = Product::query()->create([
        'slug' => 'promotion-redemption-product-'.Product::query()->count(),
        'name' => 'Promotion redemption product',
        'price' => Price::of(1000),
        'stock' => null,
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    return app(Checkout::class)->create($cart, [
        'customer_email' => 'redemptions@example.com',
        'customer_name' => 'Promotion Customer',
    ])->order;
}

it('stores promotion redemptions for an order', function () {
    $order = promotionRedemptionOrder();
    $expiresAt = Carbon::parse('2026-08-31 12:00:00');

    $redemption = PromotionRedemption::query()->create([
        'order_id' => $order->id,
        'promotion_identifier' => 'summer-sale',
        'customer_identifier' => 'redemptions@example.com',
        'status' => PromotionRedemptionStatus::Reserved,
        'expires_at' => $expiresAt,
    ]);

    expect($redemption->status)->toBe(PromotionRedemptionStatus::Reserved)
        ->and($redemption->expires_at?->toDateTimeString())->toBe($expiresAt->toDateTimeString())
        ->and($redemption->redeemed_at)->toBeNull()
        ->and($redemption->released_at)->toBeNull()
        ->and($redemption->order->is($order))->toBeTrue()
        ->and($order->promotionRedemptions()->sole()->is($redemption))->toBeTrue();
});

it('allows only one redemption per promotion and order', function () {
    $order = promotionRedemptionOrder();
    $attributes = [
        'order_id' => $order->id,
        'promotion_identifier' => 'limited-sale',
        'status' => PromotionRedemptionStatus::Reserved,
    ];

    PromotionRedemption::query()->create($attributes);

    expect(fn () => PromotionRedemption::query()->create($attributes))
        ->toThrow(QueryException::class);
});
