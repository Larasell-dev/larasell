<?php

use Illuminate\Support\Collection;
use Larasell\Larasell\Contracts\Promotion;
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
