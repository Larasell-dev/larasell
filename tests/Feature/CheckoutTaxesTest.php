<?php

use Larasell\Larasell\Address;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingMethod;
use Larasell\Larasell\Taxes\ConfiguredTaxCalculator;
use Larasell\Larasell\Taxes\Exceptions\TaxCalculationException;

final class CheckoutTaxShippingMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('checkout-tax-shipping', 'Checkout tax shipping', Price::of(500));
    }
}

final class CheckoutTaxPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'checkout-tax-discount',
            name: 'Checkout tax discount',
            allocations: $context->fixedAmountOff(Price::of(100)),
        );
    }
}

it('authoritatively calculates exclusive tax and persists immutable snapshots', function () {
    configureCheckoutTaxes(TaxPriceMode::Exclusive);
    $product = checkoutTaxProduct(1000);
    $cart = checkoutTaxCart($product);

    $order = app(Checkout::class)->create($cart, checkoutTaxData())->order;
    $item = $order->items()->sole();

    expect($order->subtotal->amount())->toBe('1000')
        ->and($order->discount_total->amount())->toBe('0')
        ->and($order->tax_price_mode)->toBe(TaxPriceMode::Exclusive)
        ->and($order->tax_total?->amount())->toBe('190')
        ->and($order->total->amount())->toBe('1190')
        ->and($order->payments()->sole()->amount->amount())->toBe('1190')
        ->and($order->tax_snapshot['status'])->toBe('calculated')
        ->and($order->tax_snapshot['price_mode'])->toBe('exclusive')
        ->and($order->tax_snapshot['components'][0]['rate'])->toBe('19.0000')
        ->and($item->tax_category)->toBe('standard')
        ->and($item->taxable_amount?->amount())->toBe('1000')
        ->and($item->tax_total?->amount())->toBe('190')
        ->and($item->tax_snapshot['gross_amount']['amount'])->toBe('1000')
        ->and($item->tax_snapshot['tax_amount']['amount'])->toBe('190');

    config()->set('larasell.taxes.rates.DE.standard.rate', '20.0000');
    $product->update(['tax_category' => 'reduced']);
    $order = $order->fresh();
    $item = $order->items()->sole();

    expect($order->tax_total?->amount())->toBe('190')
        ->and($order->tax_snapshot['components'][0]['rate'])->toBe('19.0000')
        ->and($item->tax_category)->toBe('standard')
        ->and($item->tax_snapshot['category'])->toBe('standard');
});

it('extracts inclusive tax without increasing the checkout total', function () {
    configureCheckoutTaxes(TaxPriceMode::Inclusive);
    $order = app(Checkout::class)->create(
        checkoutTaxCart(checkoutTaxProduct(1190)),
        checkoutTaxData(),
    )->order;

    expect($order->subtotal->amount())->toBe('1190')
        ->and($order->tax_total?->amount())->toBe('190')
        ->and($order->total->amount())->toBe('1190')
        ->and($order->items()->sole()->taxable_amount?->amount())->toBe('1000')
        ->and($order->items()->sole()->tax_snapshot['price_mode'])->toBe('inclusive');
});

it('recalculates tax from authoritative checkout discounts', function () {
    configureCheckoutTaxes(TaxPriceMode::Exclusive);
    app(PromotionManager::class)->register(CheckoutTaxPromotion::class);

    $order = app(Checkout::class)->create(
        checkoutTaxCart(checkoutTaxProduct(1000)),
        checkoutTaxData(),
    )->order;
    $item = $order->items()->sole();

    expect($order->discount_total->amount())->toBe('100')
        ->and($order->tax_total?->amount())->toBe('171')
        ->and($order->total->amount())->toBe('1071')
        ->and($item->discount_total->amount())->toBe('100')
        ->and($item->taxable_amount?->amount())->toBe('900')
        ->and($item->tax_snapshot['discount_amount']['amount'])->toBe('100')
        ->and($item->tax_snapshot['tax_amount']['amount'])->toBe('171');
});

it('persists shipping tax separately and in the aggregate snapshot', function () {
    configureCheckoutTaxes(TaxPriceMode::Exclusive);
    app(ShippingManager::class)->register(CheckoutTaxShippingMethod::class);
    $cart = checkoutTaxCart(checkoutTaxProduct(1000));
    $cart->selectShippingOption('checkout-tax-shipping');

    $order = app(Checkout::class)->create($cart, checkoutTaxData())->order;
    $components = collect($order->tax_snapshot['components'])->keyBy('identifier');

    expect($order->shipping_price?->amount())->toBe('500')
        ->and($order->shipping_tax_total?->amount())->toBe('95')
        ->and($order->shipping_tax_snapshot['category'])->toBe('shipping')
        ->and($order->shipping_tax_snapshot['tax_amount']['amount'])->toBe('95')
        ->and($order->tax_total?->amount())->toBe('285')
        ->and($order->total->amount())->toBe('1785')
        ->and($components['de-vat-standard']['amount']['amount'])->toBe('190')
        ->and($components['de-vat-shipping']['amount']['amount'])->toBe('95');
});

it('rolls back checkout when authoritative tax is unavailable', function () {
    configureCheckoutTaxes(TaxPriceMode::Exclusive);
    $cart = checkoutTaxCart(checkoutTaxProduct(1000));
    $data = checkoutTaxData();
    $data['billing_address'] = null;
    $data['shipping_address'] = null;

    expect(fn () => app(Checkout::class)->create($cart, $data))
        ->toThrow(TaxCalculationException::class, 'A shipping or billing address is required');

    expect(Order::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and($cart->fresh()->items)->toHaveCount(1);
});

it('rejects provisional tax during checkout', function () {
    configureCheckoutTaxes(TaxPriceMode::Exclusive);
    $cart = checkoutTaxCart(checkoutTaxProduct(1000));
    $data = checkoutTaxData();
    $data['shipping_address'] = null;

    expect(fn () => app(Checkout::class)->create($cart, $data))
        ->toThrow(TaxCalculationException::class, 'billing address');

    expect(Order::query()->count())->toBe(0)
        ->and($cart->fresh()->items)->toHaveCount(1);
});

function configureCheckoutTaxes(TaxPriceMode $mode): void
{
    config()->set('larasell.taxes.calculator', ConfiguredTaxCalculator::class);
    config()->set('larasell.taxes.price_mode', $mode->value);
}

function checkoutTaxProduct(int $price): Product
{
    return Product::query()->create([
        'slug' => 'checkout-tax-product-'.fake()->unique()->numerify('######'),
        'name' => 'Checkout tax product',
        'price' => Price::of($price),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
        'tax_category' => 'standard',
    ]);
}

function checkoutTaxCart(Product $product): Cart
{
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    return $cart;
}

/** @return array<string, mixed> */
function checkoutTaxData(): array
{
    return [
        'customer_email' => 'tax-buyer@example.com',
        'customer_name' => 'Tax Buyer',
        'billing_address' => new Address('DE', 'Tax', 'Buyer', 'Main Street 1', 'Berlin', '10115'),
        'shipping_address' => new Address('DE', 'Tax', 'Buyer', 'Shipping Street 1', 'Berlin', '10117'),
    ];
}
