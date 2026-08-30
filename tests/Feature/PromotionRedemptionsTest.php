<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\Promotions\HasRedemptionLimit;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Contracts\Promotions\PromotionCustomerResolver;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\PromotionRedemption;
use Larasell\Larasell\Price;
use Larasell\Larasell\Promotions\ReleaseExpiredPromotionRedemptionsForOrder;

final class LimitedPromotion implements HasRedemptionLimit, Promotion
{
    public static int|array $limit = 1;

    public function limit(): int|array
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

final class TestPromotionCustomerResolver implements PromotionCustomerResolver
{
    public function resolve(?int $customerId, string $email): string
    {
        return 'resolved:'.strstr($email, '@', true);
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

it('rejects invalid structured promotion redemption limits', function (array $limit) {
    LimitedPromotion::$limit = $limit;
    app(PromotionManager::class)->register(LimitedPromotion::class);

    expect(fn () => promotionRedemptionOrder())->toThrow(InvalidArgumentException::class);
})->with([
    'empty limits' => [[]],
    'zero global limit' => [['global' => 0]],
    'non-integer customer limit' => [['customer' => '1']],
    'unknown limit' => [['order' => 1]],
]);

it('enforces a redemption limit per customer', function () {
    LimitedPromotion::$limit = ['global' => 3, 'customer' => 1];
    app(PromotionManager::class)->register(LimitedPromotion::class);
    promotionRedemptionOrder();
    $repeatCustomerCart = promotionRedemptionCart();

    expect(fn () => app(Checkout::class)->create($repeatCustomerCart, [
        'customer_email' => 'REDEMPTIONS@example.com',
        'customer_name' => 'Repeat Customer',
    ]))->toThrow(
        InvalidArgumentException::class,
        'Promotion [limited-promotion] has reached its customer redemption limit.',
    );

    $otherCustomerOrder = app(Checkout::class)->create(promotionRedemptionCart(), [
        'customer_email' => 'other@example.com',
        'customer_name' => 'Other Customer',
    ])->order;

    expect($repeatCustomerCart->fresh()->items)->toHaveCount(1)
        ->and($otherCustomerOrder->promotionRedemptions()->sole()->customer_identifier)->toBe('email:other@example.com');

    $this->assertDatabaseHas('larasell_promotion_redemption_counters', [
        'promotion_identifier' => 'limited-promotion',
        'reserved_count' => 2,
    ]);
    $this->assertDatabaseHas('larasell_promotion_customer_redemption_counters', [
        'promotion_identifier' => 'limited-promotion',
        'customer_identifier' => 'email:redemptions@example.com',
        'reserved_count' => 1,
    ]);
    $this->assertDatabaseHas('larasell_promotion_customer_redemption_counters', [
        'promotion_identifier' => 'limited-promotion',
        'customer_identifier' => 'email:other@example.com',
        'reserved_count' => 1,
    ]);
});

it('supports customer-only redemption limits and a custom customer resolver', function () {
    LimitedPromotion::$limit = ['customer' => 1];
    $this->app->bind(PromotionCustomerResolver::class, TestPromotionCustomerResolver::class);
    app(PromotionManager::class)->register(LimitedPromotion::class);

    $order = app(Checkout::class)->create(promotionRedemptionCart(), [
        'customer_email' => 'buyer@example.com',
        'customer_name' => 'Buyer',
    ])->order;

    expect($order->promotionRedemptions()->sole()->customer_identifier)->toBe('resolved:buyer');

    $this->assertDatabaseMissing('larasell_promotion_redemption_counters', [
        'promotion_identifier' => 'limited-promotion',
    ]);
    $this->assertDatabaseHas('larasell_promotion_customer_redemption_counters', [
        'promotion_identifier' => 'limited-promotion',
        'customer_identifier' => 'resolved:buyer',
        'reserved_count' => 1,
    ]);
});

it('uses the customer id as the default redemption identity when available', function () {
    LimitedPromotion::$limit = ['customer' => 1];
    app(PromotionManager::class)->register(LimitedPromotion::class);

    $order = app(Checkout::class)->create(promotionRedemptionCart(), [
        'customer_id' => 42,
        'customer_email' => 'buyer@example.com',
        'customer_name' => 'Buyer',
    ])->order;

    expect($order->promotionRedemptions()->sole()->customer_identifier)->toBe('customer:42');
});

it('redeems reserved promotion capacity when payment succeeds', function () {
    $this->travelTo(Carbon::parse('2026-08-30 12:00:00'));
    LimitedPromotion::$limit = ['global' => 2, 'customer' => 2];
    app(PromotionManager::class)->register(LimitedPromotion::class);
    $order = promotionRedemptionOrder();
    $expiresAt = $order->promotionRedemptions()->sole()->expires_at;

    $this->travelTo(Carbon::parse('2026-08-30 12:15:00'));
    $payment = $order->payments->sole();
    $payment->markAsPaid();
    $payment->markAsPaid();

    $redemption = $order->promotionRedemptions()->sole();

    expect($redemption->status)->toBe(PromotionRedemptionStatus::Redeemed)
        ->and($redemption->redeemed_at?->toDateTimeString())->toBe('2026-08-30 12:15:00')
        ->and($redemption->released_at)->toBeNull()
        ->and($redemption->expires_at?->toDateTimeString())->toBe($expiresAt?->toDateTimeString());

    $this->assertDatabaseHas('larasell_promotion_redemption_counters', [
        'promotion_identifier' => 'limited-promotion',
        'reserved_count' => 0,
        'redeemed_count' => 1,
    ]);
    $this->assertDatabaseHas('larasell_promotion_customer_redemption_counters', [
        'promotion_identifier' => 'limited-promotion',
        'customer_identifier' => 'email:redemptions@example.com',
        'reserved_count' => 0,
        'redeemed_count' => 1,
    ]);
});

it('rolls back payment when reserved promotion capacity is inconsistent', function () {
    app(PromotionManager::class)->register(LimitedPromotion::class);
    $order = promotionRedemptionOrder();
    DB::table('larasell_promotion_redemption_counters')
        ->where('promotion_identifier', 'limited-promotion')
        ->update(['reserved_count' => 0]);

    expect(fn () => $order->payments->sole()->markAsPaid())
        ->toThrow(InvalidArgumentException::class, 'inconsistent redemption capacity');

    expect($order->payments->sole()->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->promotionRedemptions()->sole()->status)->toBe(PromotionRedemptionStatus::Reserved);
});

it('releases reserved promotion capacity when an order is cancelled', function () {
    LimitedPromotion::$limit = ['global' => 1, 'customer' => 1];
    app(PromotionManager::class)->register(LimitedPromotion::class);
    $order = promotionRedemptionOrder();

    $order->cancel();
    $order->cancel();

    $redemption = $order->promotionRedemptions()->sole();

    expect($redemption->status)->toBe(PromotionRedemptionStatus::Released)
        ->and($redemption->released_at)->not->toBeNull()
        ->and($redemption->redeemed_at)->toBeNull();

    $this->assertDatabaseHas('larasell_promotion_redemption_counters', [
        'promotion_identifier' => 'limited-promotion',
        'reserved_count' => 0,
        'redeemed_count' => 0,
    ]);
    $this->assertDatabaseHas('larasell_promotion_customer_redemption_counters', [
        'promotion_identifier' => 'limited-promotion',
        'customer_identifier' => 'email:redemptions@example.com',
        'reserved_count' => 0,
        'redeemed_count' => 0,
    ]);
});

it('releases an expired promotion redemption and cancels its unpaid order', function () {
    $this->travelTo('2026-08-30 12:00:00');
    app(PromotionManager::class)->register(LimitedPromotion::class);
    $order = promotionRedemptionOrder();
    $order->promotionRedemptions()->update(['expires_at' => now()->subMinute()]);

    $released = app(ReleaseExpiredPromotionRedemptionsForOrder::class)->handle($order->id);
    $releasedAgain = app(ReleaseExpiredPromotionRedemptionsForOrder::class)->handle($order->id);

    expect($released)->toBeTrue()
        ->and($releasedAgain)->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->cancellation_reason)->toBe('Promotion redemption expired')
        ->and($order->promotionRedemptions()->sole()->status)->toBe(PromotionRedemptionStatus::Released)
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::Cancelled);
});

it('does not release a promotion redemption before it expires', function () {
    app(PromotionManager::class)->register(LimitedPromotion::class);
    $order = promotionRedemptionOrder();
    $order->promotionRedemptions()->update(['expires_at' => now()->addMinute()]);

    expect(app(ReleaseExpiredPromotionRedemptionsForOrder::class)->handle($order->id))->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->promotionRedemptions()->sole()->status)->toBe(PromotionRedemptionStatus::Reserved);
});

it('releases expired promotion redemptions through the cleanup command', function () {
    app(PromotionManager::class)->register(LimitedPromotion::class);
    LimitedPromotion::$limit = 2;
    $expired = promotionRedemptionOrder();
    $future = promotionRedemptionOrder();
    $expired->promotionRedemptions()->update(['expires_at' => now()->subMinute()]);
    $future->promotionRedemptions()->update(['expires_at' => now()->addMinute()]);

    $this->artisan('larasell:release-expired-promotions', ['--batch-size' => 1])
        ->expectsOutput('Released promotions for 1 expired order.')
        ->assertSuccessful();

    expect($expired->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($future->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

afterEach(function () {
    LimitedPromotion::$limit = 1;
});
