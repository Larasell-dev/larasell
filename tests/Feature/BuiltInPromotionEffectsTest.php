<?php

use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Discounts\ProportionalDiscountAllocator;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingMethod;

final class FixedProductPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            'fixed-products',
            'Fixed product discount',
            $context->fixedAmountOff(
                Price::of(500),
                fn (CartItem $item): bool => str_starts_with($item->product->slug, 'eligible-'),
            ),
        );
    }
}

final class PercentageProductPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            'percentage-products',
            'Percentage product discount',
            $context->percentageOff('12.5'),
        );
    }
}

final class FixedShippingPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            'fixed-shipping',
            'Fixed shipping discount',
            $context->fixedAmountOffShipping(Price::of(1000)),
        );
    }
}

final class PercentageShippingPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            'percentage-shipping',
            'Percentage shipping discount',
            $context->percentageOffShipping(50),
        );
    }
}

final class PromotionEffectsShippingMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('promotion-effects-shipping', 'Promotion effects shipping', Price::of(700));
    }
}

it('applies a fixed amount only across eligible cart lines', function () {
    $cart = promotionEffectsCart([
        ['eligible-first', 600],
        ['excluded', 1000],
        ['eligible-second', 400],
    ]);
    $manager = app(PromotionManager::class);
    $manager->register(FixedProductPromotion::class);

    $result = $manager->apply($cart)->sole();

    expect($result->total()->amount())->toBe('500')
        ->and($result->allocations)->toHaveCount(2)
        ->and($result->allocations[0]->amount->amount())->toBe('300')
        ->and($result->allocations[1]->amount->amount())->toBe('200');
});

it('caps a fixed amount at the eligible line total', function () {
    $cart = promotionEffectsCart([['eligible-only', 300]]);
    $manager = app(PromotionManager::class);
    $manager->register(FixedProductPromotion::class);

    expect($manager->apply($cart)->sole()->total()->amount())->toBe('300');
});

it('applies decimal percentage discounts using minor-unit arithmetic', function () {
    $cart = promotionEffectsCart([
        ['first', 600],
        ['second', 400],
    ]);
    $manager = app(PromotionManager::class);
    $manager->register(PercentageProductPromotion::class);

    $result = $manager->apply($cart)->sole();

    expect($result->total()->amount())->toBe('125')
        ->and($result->allocations[0]->amount->amount())->toBe('75')
        ->and($result->allocations[1]->amount->amount())->toBe('50');
});

it('returns no shipping allocations when no shipping option is selected', function () {
    $manager = app(PromotionManager::class);
    $manager->register(FixedShippingPromotion::class);

    expect($manager->apply(promotionEffectsCart([['product', 1000]]))->sole()->allocations)->toBe([]);
});

it('caps a fixed shipping discount at the selected shipping price', function () {
    $cart = promotionEffectsCartWithShipping();
    $manager = app(PromotionManager::class);
    $manager->register(FixedShippingPromotion::class);

    $result = $manager->apply($cart)->sole();

    expect($result->total()->amount())->toBe('700')
        ->and($result->allocations)->toHaveCount(1)
        ->and($result->allocations[0]->target)->toBe('shipping');
});

it('applies a percentage discount to the selected shipping price', function () {
    $cart = promotionEffectsCartWithShipping();
    $manager = app(PromotionManager::class);
    $manager->register(PercentageShippingPromotion::class);

    expect($manager->apply($cart)->sole()->total()->amount())->toBe('350');
});

it('rejects invalid percentages', function (int|string $percentage) {
    $cart = promotionEffectsCart([['product', 1000]]);
    $context = promotionContextFor($cart);

    expect(fn () => $context->percentageOff($percentage))
        ->toThrow(InvalidArgumentException::class, 'A discount percentage must be between 0 and 100.');
})->with([
    'negative' => -1,
    'over one hundred' => 101,
    'malformed' => 'twelve',
]);

/** @param array<int, array{string, int}> $products */
function promotionEffectsCart(array $products): Cart
{
    $cart = Cart::query()->create(['currency' => Currency::EUR]);

    foreach ($products as [$slug, $price]) {
        $product = Product::query()->create([
            'slug' => $slug,
            'name' => $slug,
            'price' => Price::of($price),
            'allow_backorders' => true,
            'status' => Visibility::Visible,
        ]);
        $cart->add($product);
    }

    return $cart;
}

function promotionEffectsCartWithShipping(): Cart
{
    app(ShippingManager::class)->register(PromotionEffectsShippingMethod::class);
    $cart = promotionEffectsCart([['shipping-product', 1000]]);
    $cart->selectShippingOption('promotion-effects-shipping');

    return $cart;
}

function promotionContextFor(Cart $cart): PromotionContext
{
    $items = $cart->items()->with('product')->get();

    return new PromotionContext(
        cart: $cart,
        items: $items,
        currency: $cart->currency,
        subtotal: $cart->subtotal() ?? Price::of(0),
        shippingOption: $cart->shippingOption(),
        allocator: app(ProportionalDiscountAllocator::class),
    );
}
