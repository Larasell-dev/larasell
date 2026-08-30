<?php

use Larasell\Larasell\Contracts\Promotions\Promotion;
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

final class CartDiscountTestPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'cart-discount',
            name: 'Cart discount',
            allocations: $context->fixedAmountOff(Price::of(500)),
        );
    }
}

final class ExcessiveCartDiscountTestPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'excessive-cart-discount',
            name: 'Excessive cart discount',
            allocations: [new DiscountAllocation(
                $context->target($context->items->first()),
                Price::of(1500),
            )],
        );
    }
}

final class CartDiscountTestShippingMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('cart-discount-shipping', 'Cart discount shipping', Price::of(300));
    }
}

it('exposes applied discounts and subtracts them from the cart total', function () {
    app(PromotionManager::class)->register(CartDiscountTestPromotion::class);
    $cart = cartWithDiscountTestProduct(1000);

    expect($cart->discounts())->toHaveCount(1)
        ->and($cart->discounts()->sole()->identifier)->toBe('cart-discount')
        ->and($cart->discountTotal()->amount())->toBe('500')
        ->and($cart->total()?->amount())->toBe('500');
});

it('subtracts discounts from merchandise and shipping', function () {
    app(ShippingManager::class)->register(CartDiscountTestShippingMethod::class);
    app(PromotionManager::class)->register(CartDiscountTestPromotion::class);
    $cart = cartWithDiscountTestProduct(1000)->selectShippingOption('cart-discount-shipping');

    expect($cart->discountTotal()->amount())->toBe('500')
        ->and($cart->total()?->amount())->toBe('800');
});

it('does not allow promotion results to make the cart total negative', function () {
    app(PromotionManager::class)->register(ExcessiveCartDiscountTestPromotion::class);
    $cart = cartWithDiscountTestProduct(1000);

    expect($cart->discountTotal()->amount())->toBe('1000')
        ->and($cart->total()?->amount())->toBe('0');
});

it('keeps an empty cart total nullable and has no discount amount', function () {
    $cart = Cart::query()->create(['currency' => Currency::EUR]);

    expect($cart->discounts())->toBeEmpty()
        ->and($cart->discountTotal()->amount())->toBe('0')
        ->and($cart->total())->toBeNull();
});

function cartWithDiscountTestProduct(int $price): Cart
{
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $product = Product::query()->create([
        'slug' => 'cart-discount-product-'.$cart->getKey(),
        'name' => 'Cart discount product',
        'price' => Price::of($price),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart->add($product);

    return $cart;
}
