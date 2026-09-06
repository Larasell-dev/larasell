<?php

use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\Promotions\HasAvailability;
use Larasell\Larasell\Contracts\Promotions\HasCode;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Exceptions\Promotions\InapplicablePromotionCodeException;
use Larasell\Larasell\Exceptions\Promotions\PromotionCodeException;
use Larasell\Larasell\Exceptions\Promotions\PromotionException;
use Larasell\Larasell\Exceptions\Promotions\PromotionRedemptionException;
use Larasell\Larasell\Exceptions\Promotions\UnavailablePromotionCodeException;
use Larasell\Larasell\Exceptions\Promotions\UnknownPromotionCodeException;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

final class TenPercentPromotionCode implements HasCode, Promotion
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

final class FuturePromotionCode implements HasAvailability, HasCode, Promotion
{
    public function window(): array
    {
        return ['starts_at' => now()->addDay()];
    }

    public function code(): string
    {
        return 'FUTURE';
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'future-code',
            name: 'Future code',
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

it('rejects unknown promotion codes', function () {
    app(PromotionManager::class)->register(TenPercentPromotionCode::class);

    try {
        promotionCodeCart(2000)->applyPromotionCode('unknown');
    } catch (UnknownPromotionCodeException $exception) {
        expect($exception)->toBeInstanceOf(PromotionCodeException::class)
            ->and($exception)->toBeInstanceOf(PromotionException::class)
            ->and($exception)->not->toBeInstanceOf(PromotionRedemptionException::class)
            ->and($exception->getMessage())->toBe('Promotion code [UNKNOWN] is not registered.')
            ->and($exception->promotionCode)->toBe('UNKNOWN')
            ->and($exception->reason())->toBe('unknown_promotion_code')
            ->and($exception->context())->toBe([
                'reason' => 'unknown_promotion_code',
                'code' => 'UNKNOWN',
            ]);

        return;
    }

    $this->fail('Expected unknown promotion code exception.');
});

it('rejects currently inapplicable promotion codes', function () {
    app(PromotionManager::class)->register(TenPercentPromotionCode::class);

    try {
        promotionCodeCart(500)->applyPromotionCode('save10');
    } catch (InapplicablePromotionCodeException $exception) {
        expect($exception)->toBeInstanceOf(PromotionCodeException::class)
            ->and($exception)->getMessage()->toBe('Promotion code [SAVE10] is not applicable to this cart.')
            ->and($exception->promotionCode)->toBe('SAVE10')
            ->and($exception->reason())->toBe('inapplicable_promotion_code')
            ->and($exception->context())->toBe([
                'reason' => 'inapplicable_promotion_code',
                'code' => 'SAVE10',
            ]);

        return;
    }

    $this->fail('Expected inapplicable promotion code exception.');
});

it('rejects codes outside their promotion availability window', function () {
    app(PromotionManager::class)->register(FuturePromotionCode::class);

    try {
        promotionCodeCart(2000)->applyPromotionCode('FUTURE');
    } catch (UnavailablePromotionCodeException $exception) {
        expect($exception)->toBeInstanceOf(PromotionCodeException::class)
            ->and($exception)->getMessage()->toBe('Promotion code [FUTURE] is not currently available.')
            ->and($exception->promotionCode)->toBe('FUTURE')
            ->and($exception->startsAt?->isFuture())->toBeTrue()
            ->and($exception->endsAt)->toBeNull()
            ->and($exception->reason())->toBe('unavailable_promotion_code')
            ->and($exception->context()['reason'])->toBe('unavailable_promotion_code')
            ->and($exception->context()['code'])->toBe('FUTURE')
            ->and($exception->context()['starts_at'])->toBe($exception->startsAt?->toIso8601String())
            ->and($exception->context()['ends_at'])->toBeNull();

        return;
    }

    $this->fail('Expected unavailable promotion code exception.');
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
