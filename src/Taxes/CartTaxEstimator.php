<?php

namespace Larasell\Larasell\Taxes;

use Illuminate\Contracts\Config\Repository;
use Larasell\Larasell\Contracts\TaxCalculator;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Price;

final readonly class CartTaxEstimator
{
    public function __construct(
        private TaxCalculator $calculator,
        private Repository $config,
    ) {}

    /** @param iterable<int, DiscountResult>|null $discounts */
    public function estimate(Cart $cart, ?CartTaxEstimateRequest $request = null, ?iterable $discounts = null): CartTaxEstimate
    {
        $request ??= new CartTaxEstimateRequest;
        $priceMode = TaxPriceMode::from($this->config->get('larasell.taxes.price_mode', TaxPriceMode::Exclusive->value));
        $items = $cart->purchasableItems();

        if ($items->isEmpty()) {
            return new CartTaxEstimate(
                subtotal: null,
                discountAmount: Price::of(0),
                shippingAmount: null,
                tax: TaxResult::calculated($priceMode, []),
            );
        }

        $shipping = $cart->shippingOption();
        $discounts ??= $cart->discounts();
        $discountAmounts = $this->discountAmounts($discounts);
        $lines = $items->map(function (CartItem $item) use ($discountAmounts): TaxableLine {
            $identifier = 'line:'.$item->getKey();
            $amount = $item->total();

            return new TaxableLine(
                identifier: $identifier,
                amount: $amount,
                category: $item->variant->effectiveTaxCategory(),
                quantity: $item->quantity,
                metadata: [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                ],
                discountAmount: $this->capDiscount($discountAmounts[$identifier] ?? Price::of(0), $amount),
            );
        })->all();

        if ($shipping !== null) {
            $category = $this->config->get('larasell.taxes.shipping_category', 'shipping');

            if (! is_string($category) || trim($category) === '') {
                throw new \InvalidArgumentException('The configured shipping tax category must be a non-empty string.');
            }

            $lines[] = TaxableLine::shipping(
                identifier: 'shipping',
                amount: $shipping->price,
                category: $category,
                metadata: ['shipping_option' => $shipping->handle],
                discountAmount: $this->capDiscount($discountAmounts['shipping'] ?? Price::of(0), $shipping->price),
            );
        }

        $discountAmount = array_reduce(
            $lines,
            fn (Price $total, TaxableLine $line): Price => $total->add($line->discountAmount),
            Price::of(0),
        );

        $context = new TaxCalculationContext(
            lines: $lines,
            currency: $cart->currency,
            priceMode: $priceMode,
            shippingAddress: $request->shippingAddress,
            billingAddress: $request->billingAddress,
            originAddress: $request->originAddress,
            customerIdentifier: $request->customerIdentifier,
            transactionIdentifier: 'cart:'.$cart->getKey(),
            metadata: $request->metadata,
        );

        return new CartTaxEstimate(
            subtotal: $cart->subtotal(),
            discountAmount: $discountAmount,
            shippingAmount: $shipping?->price,
            tax: $this->calculator->calculate($context),
        );
    }

    /**
     * @param  iterable<int, DiscountResult>  $discounts
     * @return array<string, Price>
     */
    private function discountAmounts(iterable $discounts): array
    {
        $amounts = [];

        foreach ($discounts as $discount) {
            foreach ($discount->allocations as $allocation) {
                $amounts[$allocation->target] = ($amounts[$allocation->target] ?? Price::of(0))->add($allocation->amount);
            }
        }

        return $amounts;
    }

    private function capDiscount(Price $discount, Price $amount): Price
    {
        return $discount->greaterThan($amount) ? $amount : $discount;
    }
}
