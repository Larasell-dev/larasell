<?php

use Illuminate\Support\Collection;
use Larasell\Larasell\Contracts\Promotions\HasPriority;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Contracts\Promotions\ShouldBeExclusive;
use Larasell\Larasell\Discounts\DiscountAllocation;
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

final class PromotionTestShippingMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('promotion-shipping', 'Promotion shipping', Price::of(700));
    }
}

final class TestAutomaticPromotion implements Promotion
{
    public static ?PromotionContext $context = null;

    public function apply(PromotionContext $context): ?DiscountResult
    {
        self::$context = $context;

        return new DiscountResult(
            identifier: 'automatic-500',
            name: 'Automatic EUR 5 off',
            allocations: $context->allocator->allocate(Price::of(500), $context->eligibleAmounts()),
        );
    }
}

final class TestInapplicablePromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return null;
    }
}

final class TestInvalidTargetPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'invalid-target',
            name: 'Invalid target',
            allocations: [new DiscountAllocation('line:999999', Price::of(100))],
        );
    }
}

final class TestCountingPromotion implements Promotion
{
    public static int $applications = 0;

    public function apply(PromotionContext $context): ?DiscountResult
    {
        self::$applications++;

        return new DiscountResult(
            identifier: 'counting',
            name: 'Counting promotion',
            allocations: $context->allocator->allocate(Price::of(100), $context->eligibleAmounts()),
        );
    }
}

class TestLowPriorityPromotion implements HasPriority, Promotion
{
    public function priority(): int
    {
        return 10;
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult('low-priority', 'Low priority', $context->fixedAmountOff(Price::of(750)));
    }
}

class TestHighPriorityPromotion implements HasPriority, Promotion
{
    public function priority(): int
    {
        return 100;
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult('high-priority', 'High priority', $context->fixedAmountOff(Price::of(750)));
    }
}

final class TestExclusivePromotion extends TestLowPriorityPromotion implements ShouldBeExclusive
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult('exclusive', 'Exclusive', $context->fixedAmountOff(Price::of(300)));
    }
}

final class TestHighPriorityExclusivePromotion extends TestHighPriorityPromotion implements ShouldBeExclusive
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult('high-priority-exclusive', 'High priority exclusive', $context->fixedAmountOff(Price::of(400)));
    }
}

final class TestEqualPriorityExclusivePromotion extends TestLowPriorityPromotion implements ShouldBeExclusive
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult('equal-priority-exclusive', 'Equal priority exclusive', $context->fixedAmountOff(Price::of(500)));
    }
}

final class TestInapplicableExclusivePromotion implements Promotion, ShouldBeExclusive
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return null;
    }
}

beforeEach(function () {
    TestAutomaticPromotion::$context = null;
    TestCountingPromotion::$applications = 0;
});

it('applies registered promotions with a cart calculation context', function () {
    $cart = promotionCart([6000, 4000]);
    $manager = app(PromotionManager::class);
    $manager->register(TestAutomaticPromotion::class);

    $results = $manager->apply($cart);
    $context = TestAutomaticPromotion::$context;

    expect($results)->toBeInstanceOf(Collection::class)
        ->and($results)->toHaveCount(1)
        ->and($results->sole()->identifier)->toBe('automatic-500')
        ->and($results->sole()->total()->amount())->toBe('500')
        ->and($context)->not->toBeNull()
        ->and($context->cart->is($cart))->toBeTrue()
        ->and($context->currency)->toBe(Currency::EUR)
        ->and($context->subtotal->amount())->toBe('10000')
        ->and($context->items)->toHaveCount(2)
        ->and(array_keys($context->eligibleAmounts()))->toBe(
            $context->items->map(fn ($item): string => $context->target($item))->all()
        );
});

it('omits promotions that are not applicable', function () {
    $manager = app(PromotionManager::class);
    $manager->register(TestInapplicablePromotion::class);

    expect($manager->apply(promotionCart([1000])))->toBeEmpty();
});

it('includes the selected shipping option and target in the context', function () {
    app(ShippingManager::class)->register(PromotionTestShippingMethod::class);
    $cart = promotionCart([1000]);
    $cart->selectShippingOption('promotion-shipping');
    $manager = app(PromotionManager::class);
    $manager->register(TestAutomaticPromotion::class);

    $manager->apply($cart);

    expect(TestAutomaticPromotion::$context?->shippingOption?->handle)->toBe('promotion-shipping')
        ->and(TestAutomaticPromotion::$context?->shippingTarget())->toBe('shipping');
});

it('does not register the same promotion more than once', function () {
    $manager = app(PromotionManager::class);
    $manager->register(TestCountingPromotion::class);
    $manager->register(TestCountingPromotion::class);

    expect($manager->apply(promotionCart([1000])))->toHaveCount(1)
        ->and(TestCountingPromotion::$applications)->toBe(1);
});

it('rejects classes that do not implement the promotion contract', function () {
    expect(fn () => app(PromotionManager::class)->register(Product::class))
        ->toThrow(InvalidArgumentException::class, 'must implement');
});

it('rejects allocations targeting lines outside the evaluated cart', function () {
    $manager = app(PromotionManager::class);
    $manager->register(TestInvalidTargetPromotion::class);

    expect(fn () => $manager->apply(promotionCart([1000])))
        ->toThrow(InvalidArgumentException::class, 'Promotion [invalid-target] returned an invalid allocation target [line:999999].');
});

it('evaluates each cart without retaining previous results', function () {
    $manager = app(PromotionManager::class);
    $manager->register(TestAutomaticPromotion::class);

    $first = $manager->apply(promotionCart([300]))->sole();
    $second = $manager->apply(promotionCart([1000]))->sole();

    expect($first->total()->amount())->toBe('300')
        ->and($second->total()->amount())->toBe('500');
});

it('applies stackable promotions in descending priority order', function () {
    $manager = app(PromotionManager::class);
    $manager->register(TestLowPriorityPromotion::class);
    $manager->register(TestHighPriorityPromotion::class);

    $results = $manager->apply(promotionCart([1000]));

    expect($results->pluck('identifier')->all())->toBe(['high-priority', 'low-priority'])
        ->and($results[0]->total()->amount())->toBe('750')
        ->and($results[1]->total()->amount())->toBe('250');
});

it('uses only the highest-priority applicable exclusive promotion', function () {
    $manager = app(PromotionManager::class);
    $manager->register(TestLowPriorityPromotion::class);
    $manager->register(TestExclusivePromotion::class);
    $manager->register(TestHighPriorityExclusivePromotion::class);

    $results = $manager->apply(promotionCart([1000]));

    expect($results)->toHaveCount(1)
        ->and($results->sole()->identifier)->toBe('high-priority-exclusive');
});

it('uses registration order to resolve exclusive promotions with equal priority', function () {
    $manager = app(PromotionManager::class);
    $manager->register(TestEqualPriorityExclusivePromotion::class);
    $manager->register(TestExclusivePromotion::class);

    expect($manager->apply(promotionCart([1000]))->sole()->identifier)
        ->toBe('equal-priority-exclusive');
});

it('does not let an inapplicable exclusive promotion suppress other promotions', function () {
    $manager = app(PromotionManager::class);
    $manager->register(TestInapplicableExclusivePromotion::class);
    $manager->register(TestLowPriorityPromotion::class);

    expect($manager->apply(promotionCart([1000]))->sole()->identifier)
        ->toBe('low-priority');
});

/** @param array<int, int> $prices */
function promotionCart(array $prices): Cart
{
    $cart = Cart::query()->create(['currency' => Currency::EUR]);

    foreach ($prices as $index => $price) {
        $product = Product::query()->create([
            'slug' => 'promotion-product-'.$cart->getKey().'-'.$index,
            'name' => 'Promotion product '.$index,
            'price' => Price::of($price),
            'allow_backorders' => true,
            'status' => Visibility::Visible,
        ]);
        $cart->add($product);
    }

    return $cart;
}
