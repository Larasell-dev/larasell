<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\ProportionalDiscountAllocator;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Events\InventoryDecremented;
use Larasell\Larasell\Events\InventoryRestocked;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttribute;
use Larasell\Larasell\Price;

it('selects promotion lines by product variant and attribute value', function () {
    [$cart, $product, $small, $medium, $smallValue] = variantContextCart();
    $cart->add($small);
    $cart->add($medium);
    $items = $cart->purchasableItems();
    $context = new PromotionContext(
        $cart,
        $items,
        $cart->currency,
        Price::of(2000),
        null,
        app(ProportionalDiscountAllocator::class),
    );
    $smallLines = $context->forVariant($small);
    $allocations = $context->fixedAmountOff(
        Price::of(500),
        fn ($item): bool => $smallLines->contains(fn ($line): bool => $line->is($item)),
    );

    expect($context->forProduct($product))->toHaveCount(2)
        ->and($context->forVariant($small)->sole()->variant->is($small))->toBeTrue()
        ->and($context->withAttributeValue($smallValue)->sole()->variant->is($small))->toBeTrue()
        ->and($allocations)->toHaveCount(1)
        ->and($allocations[0]->target)->toBe($context->target($smallLines->sole()));
});

it('exposes variant-loaded purchasable items to shipping and integrations', function () {
    [$cart, , $small] = variantContextCart();
    $cart->add($small);

    $item = $cart->purchasableItems()->sole();

    expect($item->relationLoaded('product'))->toBeTrue()
        ->and($item->relationLoaded('variant'))->toBeTrue()
        ->and($item->variant->relationLoaded('attributeValues'))->toBeTrue()
        ->and($item->variant->attributeValues->first()->relationLoaded('attribute'))->toBeTrue();
});

it('includes the concrete variant in inventory events', function () {
    Event::fake([InventoryDecremented::class, InventoryRestocked::class]);
    [$cart, , $small] = variantContextCart();
    $cart->add($small);
    $order = app(Checkout::class)->create($cart, [
        'customer_email' => 'variant-events@example.com',
        'customer_name' => 'Variant Events',
    ])->order;

    Event::assertDispatched(InventoryDecremented::class, fn (InventoryDecremented $event): bool => $event->variant->is($small));

    $order->cancel();

    Event::assertDispatched(InventoryRestocked::class, fn (InventoryRestocked $event): bool => $event->variant?->is($small) === true);
});

function variantContextCart(): array
{
    $product = Product::create([
        'slug' => fake()->unique()->slug(),
        'name' => 'Context shirt',
        'price' => Price::of(1000),
        'stock' => 10,
        'status' => Visibility::Visible,
    ]);
    $size = ProductAttribute::create(['slug' => fake()->unique()->bothify('context-size-####'), 'name' => 'Size']);
    $smallValue = $size->values()->create(['slug' => 'small', 'name' => 'Small', 'value' => 'small']);
    $mediumValue = $size->values()->create(['slug' => 'medium', 'name' => 'Medium', 'value' => 'medium']);
    $product->attributeValues()->attach([$smallValue->id, $mediumValue->id]);
    $variants = $product->generateVariants([$size]);
    $variants->each->update(['stock' => 5, 'status' => Visibility::Visible]);

    return [Cart::create(['currency' => Currency::EUR]), $product, $variants->first(), $variants->last(), $smallValue];
}
