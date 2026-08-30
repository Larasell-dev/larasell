<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\Promotions\HasRedemptionLimit;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\PromotionRedemption;
use Larasell\Larasell\Price;

final class LimitedPromotion implements HasRedemptionLimit, Promotion
{
    public static int $limit = 1;

    public function limit(): int
    {
        return self::$limit;
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'limited-promotion',
            name: 'Limited promotion',
            allocations: $context->fixedAmountOff(Price::of(100)),
        );
    }
}

function promotionRedemptionCart(): Cart
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

    return $cart;
}

/** @return array<string, string> */
function promotionRedemptionCustomer(): array
{
    return [
        'customer_email' => 'redemptions@example.com',
        'customer_name' => 'Promotion Customer',
    ];
}

function promotionRedemptionOrder(): Order
{
    return app(Checkout::class)->create(
        promotionRedemptionCart(),
        promotionRedemptionCustomer(),
    )->order;
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

it('reserves limited promotion capacity during checkout', function () {
    $this->travelTo(Carbon::parse('2026-08-30 12:00:00'));
    config()->set('larasell.payments.methods.cash.inventory_reservation_minutes', 30);
    app(PromotionManager::class)->register(LimitedPromotion::class);

    $order = promotionRedemptionOrder();
    $redemption = $order->promotionRedemptions()->sole();

    expect($redemption->promotion_identifier)->toBe('limited-promotion')
        ->and($redemption->customer_identifier)->toBe('email:redemptions@example.com')
        ->and($redemption->status)->toBe(PromotionRedemptionStatus::Reserved)
        ->and($redemption->expires_at?->toDateTimeString())->toBe('2026-08-30 12:30:00');

    $this->assertDatabaseHas('larasell_promotion_redemption_counters', [
        'promotion_identifier' => 'limited-promotion',
        'reserved_count' => 1,
        'redeemed_count' => 0,
    ]);
});

it('rejects checkout when a promotion has no capacity left', function () {
    LimitedPromotion::$limit = 1;
    app(PromotionManager::class)->register(LimitedPromotion::class);
    promotionRedemptionOrder();
    $cart = promotionRedemptionCart();

    expect(fn () => app(Checkout::class)->create($cart, promotionRedemptionCustomer()))
        ->toThrow(InvalidArgumentException::class, 'Promotion [limited-promotion] has reached its redemption limit.');

    expect($cart->fresh()->items)->toHaveCount(1)
        ->and(PromotionRedemption::query()->count())->toBe(1);
});

it('rejects invalid promotion redemption limits', function () {
    LimitedPromotion::$limit = 0;
    app(PromotionManager::class)->register(LimitedPromotion::class);

    expect(fn () => promotionRedemptionOrder())
        ->toThrow(InvalidArgumentException::class, 'must be a positive integer');
});

afterEach(function () {
    LimitedPromotion::$limit = 1;
});
