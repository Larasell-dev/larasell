<?php

use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\CodedPromotion;
use Larasell\Larasell\Contracts\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

final class TenPercentPromotionCode implements CodedPromotion
{
    public function code(): string
    {
        return 'SAVE10';
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        if ($context->subtotal->amount() < 1000) {
            return null;
        }

        return new DiscountResult(
            identifier: 'save-ten-percent',
            name: 'Save ten percent',
            allocations: $context->percentageOff(10),
        );
    }
}

final class PromotionCodeAutomaticDiscount implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'automatic-code-test',
            name: 'Automatic code test',
            allocations: $context->fixedAmountOff(Price::of(100)),
        );
    }
}

it('only applies a coded promotion after its code is attached to the cart', function () {
    $manager = app(PromotionManager::class);
    $manager->register(TenPercentPromotionCode::class);
    $manager->register(PromotionCodeAutomaticDiscount::class);
    $cart = promotionCodeCart(2000);

    expect($cart->discounts()->pluck('identifier')->all())->toBe(['automatic-code-test'])
        ->and($cart->total()?->amount())->toBe('1900');

    $cart->applyPromotionCode(' save10 ');

    expect($cart->promotionCodes())->toBe(['SAVE10'])
        ->and($cart->fresh()->promotionCodes())->toBe(['SAVE10'])
        ->and($cart->discounts()->pluck('identifier')->all())->toBe([
            'save-ten-percent',
            'automatic-code-test',
        ])
        ->and($cart->total()?->amount())->toBe('1700');
});

it('does not attach duplicate codes and allows removing them', function () {
    app(PromotionManager::class)->register(TenPercentPromotionCode::class);
    $cart = promotionCodeCart(2000);

    $cart->applyPromotionCode('save10')->applyPromotionCode('SAVE10');
    expect($cart->promotionCodes())->toBe(['SAVE10']);

    $cart->removePromotionCode(' save10 ');
    expect($cart->promotionCodes())->toBe([])
        ->and($cart->discounts())->toBeEmpty();
});

it('rejects unknown and currently inapplicable codes', function () {
    app(PromotionManager::class)->register(TenPercentPromotionCode::class);

    expect(fn () => promotionCodeCart(2000)->applyPromotionCode('unknown'))
        ->toThrow(InvalidArgumentException::class, 'Promotion code [UNKNOWN] is not registered.')
        ->and(fn () => promotionCodeCart(500)->applyPromotionCode('save10'))
        ->toThrow(InvalidArgumentException::class, 'Promotion code [SAVE10] is not applicable to this cart.');
});

it('reevaluates attached codes when the cart changes', function () {
    app(PromotionManager::class)->register(TenPercentPromotionCode::class);
    $cart = promotionCodeCart(2000);
    $cart->applyPromotionCode('SAVE10');
    $cart->set($cart->items()->first()->product, 1);
    $cart->items()->first()->product->update(['price' => Price::of(500)]);

    expect($cart->promotionCodes())->toBe(['SAVE10'])
        ->and($cart->discounts())->toBeEmpty()
        ->and($cart->total()?->amount())->toBe('500');
});

it('includes the applied code in the order discount snapshot', function () {
    app(PromotionManager::class)->register(TenPercentPromotionCode::class);
    $cart = promotionCodeCart(2000)->applyPromotionCode('SAVE10');

    $order = app(Checkout::class)->create($cart, [
        'customer_email' => 'codes@example.com',
        'customer_name' => 'Code Customer',
    ])->order;

    expect($order->discounts[0]['identifier'])->toBe('save-ten-percent')
        ->and($order->discounts[0]['code'])->toBe('SAVE10')
        ->and($order->discount_total->amount())->toBe('200')
        ->and($order->total->amount())->toBe('1800');
});

function promotionCodeCart(int $price): Cart
{
    $product = Product::query()->create([
        'slug' => 'promotion-code-'.fake()->unique()->uuid(),
        'name' => 'Promotion code product',
        'price' => Price::of($price),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    return $cart;
}
