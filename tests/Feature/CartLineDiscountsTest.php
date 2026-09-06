<?php

use Larasell\Larasell\Contracts\Promotions\HasCode;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingMethod;

final class LineDiscountReadApiPercentagePromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'line-read-percentage',
            name: 'Ten percent off',
            allocations: $context->percentageOff(10),
        );
    }
}

final class LineDiscountReadApiEligiblePromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'line-read-eligible',
            name: 'Eligible lines only',
            allocations: $context->fixedAmountOff(
                Price::of(500),
                fn (CartItem $item): bool => str_starts_with($item->product->slug->get(), 'eligible-'),
            ),
        );
    }
}

final class LineDiscountReadApiShippingPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'line-read-shipping',
            name: 'Shipping discount',
            allocations: $context->fixedAmountOffShipping(Price::of(100)),
        );
    }
}

final class LineDiscountReadApiStackedPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'line-read-stacked',
            name: 'Stacked discount',
            allocations: $context->fixedAmountOff(Price::of(200)),
        );
    }
}

final class LineDiscountReadApiSaveTen implements HasCode, Promotion
{
    public function code(): string
    {
        return 'SAVE10';
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'line-read-save-ten',
            name: 'Save ten percent',
            allocations: $context->percentageOff(10),
        );
    }
}

final class LineDiscountReadApiShippingMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('line-read-shipping', 'Line read shipping', Price::of(300));
    }
}

it('exposes discounted totals and applied discounts on each cart line', function () {
    app(PromotionManager::class)->register(LineDiscountReadApiPercentagePromotion::class);
    $cart = lineDiscountReadApiCart([
        ['first', 600],
        ['second', 400],
    ]);
    [$first, $second] = $cart->purchasableItems()->all();

    expect($first->total()->amount())->toBe('600')
        ->and($first->discountTotal()->amount())->toBe('60')
        ->and($first->totalAfterDiscount()->amount())->toBe('540')
        ->and($first->appliedDiscounts())->toHaveCount(1)
        ->and($first->appliedDiscounts()->sole()->identifier)->toBe('line-read-percentage')
        ->and($first->appliedDiscounts()->sole()->name)->toBe('Ten percent off')
        ->and($first->appliedDiscounts()->sole()->code)->toBeNull()
        ->and($first->appliedDiscounts()->sole()->amount->amount())->toBe('60')
        ->and($second->discountTotal()->amount())->toBe('40')
        ->and($second->totalAfterDiscount()->amount())->toBe('360')
        ->and($cart->merchandiseDiscountTotal()->amount())->toBe('100')
        ->and($cart->shippingDiscountTotal()->amount())->toBe('0')
        ->and($cart->shippingTotalAfterDiscount())->toBeNull()
        ->and($cart->appliedShippingDiscounts())->toBeEmpty();
});

it('keeps discounts on eligible lines only', function () {
    app(PromotionManager::class)->register(LineDiscountReadApiEligiblePromotion::class);
    $cart = lineDiscountReadApiCart([
        ['eligible-first', 600],
        ['excluded', 1000],
        ['eligible-second', 400],
    ]);
    [$eligible, $excluded, $otherEligible] = $cart->purchasableItems()->all();

    expect($eligible->discountTotal()->amount())->toBe('300')
        ->and($eligible->totalAfterDiscount()->amount())->toBe('300')
        ->and($excluded->discountTotal()->amount())->toBe('0')
        ->and($excluded->totalAfterDiscount()->amount())->toBe('1000')
        ->and($excluded->appliedDiscounts())->toBeEmpty()
        ->and($otherEligible->discountTotal()->amount())->toBe('200')
        ->and($cart->merchandiseDiscountTotal()->amount())->toBe('500');
});

it('separates shipping discounts from merchandise line discounts', function () {
    app(ShippingManager::class)->register(LineDiscountReadApiShippingMethod::class);
    app(PromotionManager::class)->register(LineDiscountReadApiPercentagePromotion::class);
    app(PromotionManager::class)->register(LineDiscountReadApiShippingPromotion::class);
    $cart = lineDiscountReadApiCart([['product', 1000]]);
    $cart->selectShippingOption('line-read-shipping');
    $item = $cart->purchasableItems()->sole();

    expect($item->discountTotal()->amount())->toBe('100')
        ->and($item->totalAfterDiscount()->amount())->toBe('900')
        ->and($item->appliedDiscounts())->toHaveCount(1)
        ->and($cart->merchandiseDiscountTotal()->amount())->toBe('100')
        ->and($cart->shippingDiscountTotal()->amount())->toBe('100')
        ->and($cart->shippingTotalAfterDiscount()?->amount())->toBe('200')
        ->and($cart->appliedShippingDiscounts())->toHaveCount(1)
        ->and($cart->appliedShippingDiscounts()->sole()->identifier)->toBe('line-read-shipping')
        ->and($cart->appliedShippingDiscounts()->sole()->amount->amount())->toBe('100')
        ->and($cart->discountTotal()->amount())->toBe('200');
});

it('stacks multiple promotions on the same cart line', function () {
    app(PromotionManager::class)->register(LineDiscountReadApiPercentagePromotion::class);
    app(PromotionManager::class)->register(LineDiscountReadApiStackedPromotion::class);
    $item = lineDiscountReadApiCart([['product', 1000]])->purchasableItems()->sole();

    expect($item->discountTotal()->amount())->toBe('300')
        ->and($item->totalAfterDiscount()->amount())->toBe('700')
        ->and($item->appliedDiscounts())->toHaveCount(2)
        ->and($item->appliedDiscounts()->pluck('identifier')->all())->toEqualCanonicalizing([
            'line-read-percentage',
            'line-read-stacked',
        ]);
});

it('includes the promotion code on line-level applied discounts', function () {
    app(PromotionManager::class)->register(LineDiscountReadApiSaveTen::class);
    $cart = lineDiscountReadApiCart([['product', 1000]]);
    $cart->applyPromotionCode('SAVE10');
    $applied = $cart->purchasableItems()->sole()->appliedDiscounts()->sole();

    expect($applied->identifier)->toBe('line-read-save-ten')
        ->and($applied->code)->toBe('SAVE10')
        ->and($applied->amount->amount())->toBe('100');
});

it('returns zero line discounts for an empty cart', function () {
    $cart = Cart::query()->create(['currency' => Currency::EUR]);

    expect($cart->merchandiseDiscountTotal()->amount())->toBe('0')
        ->and($cart->shippingDiscountTotal()->amount())->toBe('0')
        ->and($cart->shippingTotalAfterDiscount())->toBeNull()
        ->and($cart->appliedShippingDiscounts())->toBeEmpty();
});

/** @param array<int, array{string, int}> $products */
function lineDiscountReadApiCart(array $products): Cart
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
