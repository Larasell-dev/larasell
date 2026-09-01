<?php

use Larasell\Larasell\Address;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\PaymentProvider;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Payments\PaymentBreakdownFactory;
use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingMethod;
use Larasell\Larasell\Taxes\ConfiguredTaxCalculator;

final class PaymentBreakdownProvider implements PaymentProvider
{
    public ?PaymentRequest $request = null;

    public function initiate(PaymentRequest $request): PaymentResult
    {
        $this->request = $request;

        return PaymentResult::pending('payment-breakdown');
    }
}

final class PaymentBreakdownShippingMethod extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('payment-breakdown-shipping', 'DHL Standard', Price::of(500));
    }
}

final class PaymentBreakdownPromotion implements Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            identifier: 'payment-breakdown-discount',
            name: 'Payment breakdown discount',
            allocations: [
                ...$context->fixedAmountOff(Price::of(100)),
                ...$context->fixedAmountOffShipping(Price::of(100)),
            ],
        );
    }
}

it('provides payment providers with final product and shipping amounts', function () {
    configurePaymentBreakdown(TaxPriceMode::Exclusive);
    app(PromotionManager::class)->register(PaymentBreakdownPromotion::class);
    app(ShippingManager::class)->register(PaymentBreakdownShippingMethod::class);
    $cart = paymentBreakdownCart(1000, 2);
    $cart->selectShippingOption('payment-breakdown-shipping');

    $result = app(Checkout::class)->create(
        $cart,
        paymentBreakdownCustomer(),
        'breakdown',
    );
    $request = app(PaymentBreakdownProvider::class)->request;
    $line = $request?->breakdown->lines[0];
    $shipping = $request?->breakdown->shipping;

    expect($request)->not->toBeNull()
        ->and($request->breakdown->total->amount())->toBe('2737')
        ->and($request->breakdown->total->amount())->toBe($result->payment->amount->amount())
        ->and($request->breakdown->lines)->toHaveCount(1)
        ->and($line->identifier)->toBe('order-item:'.$result->order->items->sole()->id)
        ->and($line->name)->toBe('Payment breakdown product')
        ->and($line->quantity)->toBe(2)
        ->and($line->amount->amount())->toBe('2261')
        ->and($line->metadata['sku'])->toBe($result->order->items->sole()->product_sku)
        ->and($shipping?->identifier)->toBe('shipping')
        ->and($shipping?->name)->toBe('DHL Standard')
        ->and($shipping?->quantity)->toBe(1)
        ->and($shipping?->amount->amount())->toBe('476');
});

it('does not add inclusive tax to payment lines a second time', function () {
    configurePaymentBreakdown(TaxPriceMode::Inclusive);

    $result = app(Checkout::class)->create(
        paymentBreakdownCart(1190),
        paymentBreakdownCustomer(),
        'breakdown',
    );
    $request = app(PaymentBreakdownProvider::class)->request;

    expect($request?->breakdown->lines[0]->amount->amount())->toBe('1190')
        ->and($request?->breakdown->shipping)->toBeNull()
        ->and($request?->breakdown->total->amount())->toBe('1190')
        ->and($request?->breakdown->total->amount())->toBe($result->payment->amount->amount());
});

it('rejects a breakdown that does not reconcile to the payment amount', function () {
    configurePaymentBreakdown(TaxPriceMode::Exclusive);

    $result = app(Checkout::class)->create(
        paymentBreakdownCart(1000),
        paymentBreakdownCustomer(),
        'breakdown',
    );
    $result->payment->update(['amount' => Price::of(999)]);

    expect(fn () => app(PaymentBreakdownFactory::class)->make(
        $result->order,
        $result->payment->refresh(),
    ))->toThrow(InvalidArgumentException::class, 'does not match payment amount');
});

function configurePaymentBreakdown(TaxPriceMode $priceMode): void
{
    config()->set('larasell.taxes.calculator', ConfiguredTaxCalculator::class);
    config()->set('larasell.taxes.price_mode', $priceMode->value);
    config()->set('larasell.payments.methods.breakdown', [
        'driver' => 'breakdown',
        'provider' => PaymentBreakdownProvider::class,
    ]);
    app()->singleton(PaymentBreakdownProvider::class);
}

function paymentBreakdownCart(int $price, int $quantity = 1): Cart
{
    $product = Product::query()->create([
        'slug' => 'payment-breakdown-'.fake()->unique()->numerify('######'),
        'name' => 'Payment breakdown product',
        'price' => Price::of($price),
        'sku' => 'PAYMENT-BREAKDOWN',
        'allow_backorders' => true,
        'status' => Visibility::Visible,
        'tax_category' => 'standard',
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product, $quantity);

    return $cart;
}

/** @return array<string, mixed> */
function paymentBreakdownCustomer(): array
{
    return [
        'customer_email' => 'payment-breakdown@example.com',
        'customer_name' => 'Payment Breakdown',
        'billing_address' => new Address('DE', 'Payment', 'Breakdown', 'Main Street 1', 'Berlin', '10115'),
        'shipping_address' => new Address('DE', 'Payment', 'Breakdown', 'Shipping Street 1', 'Berlin', '10117'),
    ];
}
