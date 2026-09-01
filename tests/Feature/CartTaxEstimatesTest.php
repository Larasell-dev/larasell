<?php

use Larasell\Larasell\Address;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountAllocation;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\TaxResultStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingMethod;
use Larasell\Larasell\Taxes\CartTaxEstimateRequest;
use Larasell\Larasell\Taxes\ConfiguredTaxCalculator;

final class CartTaxEstimatePromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'tax-estimate-discount',
            name: 'Tax estimate discount',
            allocations: $context->fixedAmountOff(Price::of(100)),
        );
    }
}

final class OverlappingCartTaxEstimatePromotionA implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'overlapping-tax-estimate-discount-a',
            name: 'Overlapping tax estimate discount A',
            allocations: [new DiscountAllocation($context->target($context->items->first()), Price::of(800))],
        );
    }
}

final class OverlappingCartTaxEstimatePromotionB implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'overlapping-tax-estimate-discount-b',
            name: 'Overlapping tax estimate discount B',
            allocations: [new DiscountAllocation($context->target($context->items->first()), Price::of(800))],
        );
    }
}

final class CartTaxEstimateShippingMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('tax-estimate-shipping', 'Tax estimate shipping', Price::of(500));
    }
}

it('estimates exclusive cart tax after promotion allocations', function () {
    useConfiguredCartTaxes(TaxPriceMode::Exclusive);
    app(PromotionManager::class)->register(CartTaxEstimatePromotion::class);
    $cart = cartForTaxEstimate(1000);

    $estimate = $cart->taxEstimate(new CartTaxEstimateRequest(
        shippingAddress: cartTaxAddress('DE'),
    ));

    expect($estimate->tax->status)->toBe(TaxResultStatus::Calculated)
        ->and($estimate->subtotal?->amount())->toBe('1000')
        ->and($estimate->discountAmount->amount())->toBe('100')
        ->and($estimate->amountBeforeTax()?->amount())->toBe('900')
        ->and($estimate->tax->taxableAmount()->amount())->toBe('900')
        ->and($estimate->tax->taxAmount()->amount())->toBe('171')
        ->and($estimate->total()?->amount())->toBe('1071');
});

it('keeps inclusive totals unchanged while extracting tax after discounts', function () {
    useConfiguredCartTaxes(TaxPriceMode::Inclusive);
    app(PromotionManager::class)->register(CartTaxEstimatePromotion::class);
    $cart = cartForTaxEstimate(1190);

    $estimate = $cart->taxEstimate(new CartTaxEstimateRequest(
        shippingAddress: cartTaxAddress('DE'),
    ));

    expect($estimate->amountBeforeTax()?->amount())->toBe('1090')
        ->and($estimate->tax->taxAmount()->amount())->toBe('174')
        ->and($estimate->total()?->amount())->toBe('1090');
});

it('uses promotion allocations after cumulative target caps', function () {
    useConfiguredCartTaxes(TaxPriceMode::Exclusive);
    app(PromotionManager::class)->register(OverlappingCartTaxEstimatePromotionA::class);
    app(PromotionManager::class)->register(OverlappingCartTaxEstimatePromotionB::class);
    $cart = cartForTaxEstimate(1000);
    $secondProduct = Product::query()->create([
        'slug' => 'second-tax-estimate-product-'.$cart->getKey(),
        'name' => 'Second tax estimate product',
        'price' => Price::of(1000),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
        'tax_category' => 'standard',
    ]);
    $cart->add($secondProduct);

    $estimate = $cart->taxEstimate(new CartTaxEstimateRequest(
        shippingAddress: cartTaxAddress('DE'),
    ));

    expect($estimate->discountAmount->amount())->toBe('1000')
        ->and($estimate->tax->lines[0]->discountAmount->amount())->toBe('1000')
        ->and($estimate->tax->lines[1]->discountAmount->amount())->toBe('0')
        ->and($estimate->amountBeforeTax()?->amount())->toBe('1000')
        ->and($estimate->total()?->amount())->toBe('1190');
});

it('includes selected shipping as its own taxable estimate line', function () {
    useConfiguredCartTaxes(TaxPriceMode::Exclusive);
    app(ShippingManager::class)->register(CartTaxEstimateShippingMethod::class);
    $cart = cartForTaxEstimate(1000)->selectShippingOption('tax-estimate-shipping');

    $estimate = $cart->taxEstimate(new CartTaxEstimateRequest(
        shippingAddress: cartTaxAddress('DE'),
    ));

    expect($estimate->shippingAmount?->amount())->toBe('500')
        ->and($estimate->tax->lines)->toHaveCount(2)
        ->and($estimate->tax->lines[1]->lineIdentifier)->toBe('shipping')
        ->and($estimate->tax->lines[1]->taxAmount->amount())->toBe('95')
        ->and($estimate->total()?->amount())->toBe('1785');
});

it('returns unavailable tax and no exclusive payable total without an address', function () {
    useConfiguredCartTaxes(TaxPriceMode::Exclusive);
    $estimate = cartForTaxEstimate(1000)->taxEstimate();

    expect($estimate->tax->status)->toBe(TaxResultStatus::Unavailable)
        ->and($estimate->amountBeforeTax()?->amount())->toBe('1000')
        ->and($estimate->total())->toBeNull();
});

it('returns a provisional estimate from the billing fallback', function () {
    useConfiguredCartTaxes(TaxPriceMode::Exclusive);
    $estimate = cartForTaxEstimate(1000)->taxEstimate(new CartTaxEstimateRequest(
        billingAddress: cartTaxAddress('DE'),
    ));

    expect($estimate->tax->status)->toBe(TaxResultStatus::Provisional)
        ->and($estimate->tax->taxAmount()->amount())->toBe('190')
        ->and($estimate->total()?->amount())->toBe('1190');
});

it('preserves explicit no-tax behavior without requiring an address', function () {
    $estimate = cartForTaxEstimate(1000)->taxEstimate();

    expect($estimate->tax->status)->toBe(TaxResultStatus::Calculated)
        ->and($estimate->tax->taxAmount()->amount())->toBe('0')
        ->and($estimate->total()?->amount())->toBe('1000');
});

it('keeps empty cart estimates nullable', function () {
    $estimate = Cart::query()->create(['currency' => Currency::EUR])->taxEstimate();

    expect($estimate->subtotal)->toBeNull()
        ->and($estimate->amountBeforeTax())->toBeNull()
        ->and($estimate->total())->toBeNull()
        ->and($estimate->tax->status)->toBe(TaxResultStatus::Calculated);
});

function useConfiguredCartTaxes(TaxPriceMode $mode): void
{
    config()->set('larasell.taxes.calculator', ConfiguredTaxCalculator::class);
    config()->set('larasell.taxes.price_mode', $mode->value);
}

function cartForTaxEstimate(int $price): Cart
{
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $product = Product::query()->create([
        'slug' => 'tax-estimate-product-'.$cart->getKey(),
        'name' => 'Tax estimate product',
        'price' => Price::of($price),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
        'tax_category' => 'standard',
    ]);
    $cart->add($product);

    return $cart;
}

function cartTaxAddress(string $country): Address
{
    return new Address($country, 'Ada', 'Lovelace', 'Main Street 1', 'Berlin', '10115');
}
