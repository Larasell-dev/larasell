<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Carts\CartMergeAttributePlan;
use Larasell\Larasell\Carts\CartMergeContext;
use Larasell\Larasell\Carts\CartMergeLineIntent;
use Larasell\Larasell\Carts\CartMergeLinePlan;
use Larasell\Larasell\Carts\CartMergePlan;
use Larasell\Larasell\Carts\Strategies\FailOnConflict;
use Larasell\Larasell\Carts\Strategies\KeepDestination;
use Larasell\Larasell\Carts\Strategies\PreferDestination;
use Larasell\Larasell\Carts\Strategies\PreferSource;
use Larasell\Larasell\Carts\Strategies\ReplaceDestination;
use Larasell\Larasell\Contracts\Promotions\HasCode;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Events\CartMerged;
use Larasell\Larasell\Exceptions\Cart\CartMergeException;
use Larasell\Larasell\Exceptions\Cart\CartQuantityExceedsMaximumException;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingMethod;

final class CartMergeSaveTenPercent implements HasCode, Promotion
{
    public function code(): string
    {
        return 'SAVE10';
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'cart-merge-save-ten-percent',
            name: 'Save ten percent',
            allocations: $context->percentageOff(10),
        );
    }
}

final class CartMergeVip implements HasCode, Promotion
{
    public function code(): string
    {
        return 'VIP';
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'cart-merge-vip',
            name: 'VIP',
            allocations: $context->fixedAmountOff(Price::of(100)),
        );
    }
}

final class CartMergeSingleItemOnly implements HasCode, Promotion
{
    public function code(): string
    {
        return 'SINGLE';
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        if ($context->items->sum('quantity') !== 1) {
            return null;
        }

        return new DiscountResult(
            identifier: 'cart-merge-single-item-only',
            name: 'Single item only',
            allocations: $context->fixedAmountOff(Price::of(100)),
        );
    }
}

final class CartMergeShippingMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('standard', 'Standard shipping', Price::of(500));

        if ($cart->quantity() < 5) {
            $this->register('express', 'Express shipping', Price::of(1200));
        }
    }
}

it('combines matching lines by default and deletes the source cart', function () {
    $product = cartMergeProduct();
    $otherProduct = cartMergeProduct();
    $destination = Cart::query()->create(['currency' => Currency::EUR, 'user_id' => 42]);
    $source = Cart::query()->create(['currency' => Currency::EUR]);

    $destination->add($product, 2);
    $source->add($product, 3);
    $source->add($otherProduct, 1);

    Event::fake([CartMerged::class]);

    $result = $destination->merge($source);

    expect($result->cart->is($destination))->toBeTrue()
        ->and($destination->fresh()->quantity())->toBe(6)
        ->and(Cart::query()->find($source->getKey()))->toBeNull();

    Event::assertDispatched(CartMerged::class);
});

it('keeps cart item metadata as part of line identity when merging', function () {
    $product = cartMergeProduct();
    $destination = Cart::query()->create(['currency' => Currency::EUR]);
    $source = Cart::query()->create(['currency' => Currency::EUR]);

    $destination->add($product, 1, ['belongs_to' => 'Alice']);
    $source->add($product, 2, ['belongs_to' => 'Bob']);

    $destination->merge($source);

    $lines = $destination->fresh()->items()->orderBy('id')->get();

    expect($lines)->toHaveCount(2)
        ->and($lines->pluck('quantity')->all())->toBe([1, 2]);
});

it('prefers destination quantities on conflicts', function () {
    [$destination, $source, $shared, $destinationOnly, $sourceOnly] = cartMergeStrategyCarts();

    $destination->merge($source, new PreferDestination);

    expect(cartMergeQuantities($destination))->toBe([
        $shared->id => 2,
        $destinationOnly->id => 1,
        $sourceOnly->id => 4,
    ]);
});

it('prefers source quantities on conflicts', function () {
    [$destination, $source, $shared, $destinationOnly, $sourceOnly] = cartMergeStrategyCarts();

    $destination->merge($source, new PreferSource);

    expect(cartMergeQuantities($destination))->toBe([
        $shared->id => 5,
        $destinationOnly->id => 1,
        $sourceOnly->id => 4,
    ]);
});

it('replaces destination lines when requested', function () {
    [$destination, $source, $shared, $destinationOnly, $sourceOnly] = cartMergeStrategyCarts();

    $destination->merge($source, new ReplaceDestination);

    expect(cartMergeQuantities($destination))->toBe([
        $shared->id => 5,
        $sourceOnly->id => 4,
    ])->and($destination->fresh()->items()->where('product_id', $destinationOnly->id)->exists())->toBeFalse();
});

it('can keep destination lines and discard source lines', function () {
    [$destination, $source, $shared, $destinationOnly, $sourceOnly] = cartMergeStrategyCarts();

    $destination->merge($source, new KeepDestination);

    expect(cartMergeQuantities($destination))->toBe([
        $shared->id => 2,
        $destinationOnly->id => 1,
    ])->and($destination->fresh()->items()->where('product_id', $sourceOnly->id)->exists())->toBeFalse();
});

it('accepts a callback strategy', function () {
    $product = cartMergeProduct();
    $destination = Cart::query()->create(['currency' => Currency::EUR]);
    $source = Cart::query()->create(['currency' => Currency::EUR]);
    $destination->add($product, 2);
    $source->add($product, 5);

    $destination->merge($source, fn (CartMergeContext $context): CartMergePlan => new CartMergePlan(
        lines: new CartMergeLinePlan([
            new CartMergeLineIntent($context->sourceItems()->sole()->variant, 1),
        ]),
        attributes: CartMergeAttributePlan::preferSource($context),
    ));

    expect($destination->fresh()->items()->sole()->quantity)->toBe(1);
});

it('clamps merged quantities and records adjustments', function () {
    $product = cartMergeProduct([
        'allow_backorders' => false,
        'stock' => 10,
    ]);
    $destination = Cart::query()->create(['currency' => Currency::EUR]);
    $source = Cart::query()->create(['currency' => Currency::EUR]);

    $destination->add($product, 8);
    $source->add($product, 2);
    $source->items()->first()->update(['quantity' => 5]);

    $result = $destination->merge($source);

    expect($destination->fresh()->items()->sole()->quantity)->toBe(10)
        ->and($result->adjustedLines)->toHaveCount(1)
        ->and($result->adjustedLines[0]->reason)->toBe('insufficient_stock')
        ->and($result->adjustedLines[0]->requestedQuantity)->toBe(13)
        ->and($result->adjustedLines[0]->acceptedQuantity)->toBe(10);
});

it('skips unavailable source lines', function () {
    $product = cartMergeProduct();
    $destination = Cart::query()->create(['currency' => Currency::EUR]);
    $source = Cart::query()->create(['currency' => Currency::EUR]);
    $source->add($product, 1);
    $product->update(['status' => Visibility::Hidden]);

    $result = $destination->merge($source);

    expect($destination->fresh()->items)->toHaveCount(0)
        ->and($result->skippedLines)->toHaveCount(1)
        ->and($result->skippedLines[0]->reason)->toBe('unavailable_cart_item');
});

it('can fail instead of clamping conflicts', function () {
    $product = cartMergeProduct(['max_quantity' => 5]);
    $destination = Cart::query()->create(['currency' => Currency::EUR]);
    $source = Cart::query()->create(['currency' => Currency::EUR]);
    $destination->add($product, 3);
    $source->add($product, 2);
    $source->items()->first()->update(['quantity' => 3]);

    expect(fn () => $destination->merge($source, new FailOnConflict))
        ->toThrow(CartQuantityExceedsMaximumException::class);

    expect($destination->fresh()->items()->sole()->quantity)->toBe(3)
        ->and(Cart::query()->find($source->getKey()))->not->toBeNull();
});

it('rejects carts with different currencies', function () {
    $destination = Cart::query()->create(['currency' => Currency::EUR]);
    $source = Cart::query()->create(['currency' => Currency::USD]);

    expect(fn () => $destination->merge($source))
        ->toThrow(CartMergeException::class, 'Carts with different currencies cannot be merged.');
});

it('rejects carts owned by different users', function () {
    $destination = Cart::query()->create(['currency' => Currency::EUR, 'user_id' => 1]);
    $source = Cart::query()->create(['currency' => Currency::EUR, 'user_id' => 2]);

    expect(fn () => $destination->merge($source))
        ->toThrow(CartMergeException::class, 'A cart owned by another user cannot be merged.');
});

it('merges attributes, revalidates promotion codes, and falls back to available shipping', function () {
    app(PromotionManager::class)->register(CartMergeSaveTenPercent::class);
    app(PromotionManager::class)->register(CartMergeVip::class);
    app(ShippingManager::class)->register(CartMergeShippingMethod::class);

    $product = cartMergeProduct();
    $destination = Cart::query()->create([
        'currency' => Currency::EUR,
        'metadata' => ['gift' => true],
    ]);
    $source = Cart::query()->create([
        'currency' => Currency::EUR,
        'metadata' => ['gift' => false, 'note' => 'guest'],
    ]);

    $destination->add($product, 2);
    $source->add($product, 4);
    $destination->applyPromotionCode('SAVE10');
    $source->applyPromotionCode('VIP');
    $destination->selectShippingOption('express');
    $source->selectShippingOption('standard');

    $result = $destination->merge($source);
    $merged = $result->cart;

    expect($merged->metadata->all())->toBe(['gift' => true, 'note' => 'guest'])
        ->and($merged->promotionCodes())->toBe(['SAVE10', 'VIP'])
        ->and($merged->shipping_option)->toBe('standard')
        ->and($result->droppedPromotionCodes)->toBe([]);
});

it('drops promotion codes that no longer apply after merging', function () {
    app(PromotionManager::class)->register(CartMergeSingleItemOnly::class);

    $product = cartMergeProduct();
    $destination = Cart::query()->create(['currency' => Currency::EUR]);
    $source = Cart::query()->create(['currency' => Currency::EUR]);

    $destination->add($product, 1);
    $source->add($product, 1);
    $source->applyPromotionCode('SINGLE');

    $result = $destination->merge($source);
    $merged = $result->cart;

    expect($merged->promotionCodes())->toBe([])
        ->and($result->droppedPromotionCodes)->toBe(['SINGLE']);
});

function cartMergeProduct(array $attributes = []): Product
{
    return Product::query()->create(array_merge([
        'slug' => fake()->unique()->slug(),
        'name' => fake()->words(3, true),
        'price' => Price::of(1000),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ], $attributes));
}

/**
 * @return array{Cart, Cart, Product, Product, Product}
 */
function cartMergeStrategyCarts(): array
{
    $shared = cartMergeProduct();
    $destinationOnly = cartMergeProduct();
    $sourceOnly = cartMergeProduct();
    $destination = Cart::query()->create(['currency' => Currency::EUR]);
    $source = Cart::query()->create(['currency' => Currency::EUR]);

    $destination->add($shared, 2);
    $destination->add($destinationOnly, 1);
    $source->add($shared, 5);
    $source->add($sourceOnly, 4);

    return [$destination, $source, $shared, $destinationOnly, $sourceOnly];
}

/**
 * @return array<int, int>
 */
function cartMergeQuantities(Cart $cart): array
{
    return $cart->fresh()
        ->items()
        ->orderBy('product_id')
        ->pluck('quantity', 'product_id')
        ->all();
}
