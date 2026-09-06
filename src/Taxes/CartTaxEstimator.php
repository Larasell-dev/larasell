<?php

namespace Larasell\Larasell\Taxes;

use Illuminate\Contracts\Config\Repository;
use Larasell\Larasell\Address;
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

    /**
     * @param  array<string, mixed>  $metadata
     * @param  iterable<int, DiscountResult>|null  $discounts
     */
    public function estimate(
        Cart $cart,
        ?Address $shippingAddress = null,
        ?Address $billingAddress = null,
        ?Address $originAddress = null,
        ?string $customerIdentifier = null,
        array $metadata = [],
        ?iterable $discounts = null,
    ): CartTaxEstimate {
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
        $lines = $items->map(function (CartItem $item) use ($discounts): TaxableLine {
            $amount = $item->total();

            return new TaxableLine(
                identifier: 'line:'.$item->getKey(),
                amount: $amount,
                category: $item->variant->effectiveTaxCategory(),
                quantity: $item->quantity,
                metadata: [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                ],
                discountAmount: $this->capDiscount($this->allocatedAmount(
                    $discounts,
                    fn (DiscountResult $discount): Price => $discount->amountFor($item),
                ), $amount),
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
                discountAmount: $this->capDiscount($this->allocatedAmount(
                    $discounts,
                    fn (DiscountResult $discount): Price => $discount->amountForShipping(),
                ), $shipping->price),
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
            shippingAddress: $shippingAddress,
            billingAddress: $billingAddress,
            originAddress: $originAddress,
            customerIdentifier: $customerIdentifier,
            transactionIdentifier: 'cart:'.$cart->getKey(),
            metadata: $metadata,
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
     * @param  callable(DiscountResult): Price  $amount
     */
    private function allocatedAmount(iterable $discounts, callable $amount): Price
    {
        $total = Price::of(0);

        foreach ($discounts as $discount) {
            $total = $total->add($amount($discount));
        }

        return $total;
    }

    private function capDiscount(Price $discount, Price $amount): Price
    {
        return $discount->greaterThan($amount) ? $amount : $discount;
    }
}
