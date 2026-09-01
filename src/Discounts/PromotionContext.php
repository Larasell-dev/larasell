<?php

namespace Larasell\Larasell\Discounts;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductAttributeValue;
use Larasell\Larasell\Models\ProductVariant;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingOption;

final readonly class PromotionContext
{
    /** @param Collection<int, CartItem> $items */
    public function __construct(
        public Cart $cart,
        public Collection $items,
        public Currency $currency,
        public Price $subtotal,
        public ?ShippingOption $shippingOption,
        public ProportionalDiscountAllocator $allocator,
    ) {}

    public function target(CartItem $item): string
    {
        if (! $this->items->contains(fn (CartItem $candidate): bool => $candidate->is($item))) {
            throw new InvalidArgumentException('A promotion target must belong to the evaluated cart.');
        }

        return 'line:'.$item->getKey();
    }

    /** @return Collection<int, CartItem> */
    public function forProduct(Product $product): Collection
    {
        return $this->items->filter(
            fn (CartItem $item): bool => $item->product_id === $product->getKey(),
        )->values();
    }

    /** @return Collection<int, CartItem> */
    public function forVariant(ProductVariant $variant): Collection
    {
        return $this->items->filter(
            fn (CartItem $item): bool => $item->product_variant_id === $variant->getKey(),
        )->values();
    }

    /** @return Collection<int, CartItem> */
    public function withAttributeValue(ProductAttributeValue $value): Collection
    {
        return $this->items->filter(
            fn (CartItem $item): bool => $item->variant->attributeValues->contains(
                fn (ProductAttributeValue $candidate): bool => $candidate->is($value),
            ),
        )->values();
    }

    /**
     * @param  (callable(CartItem): bool)|null  $eligible
     * @return array<string, Price>
     */
    public function eligibleAmounts(?callable $eligible = null): array
    {
        return $this->items
            ->when($eligible !== null, fn (Collection $items): Collection => $items->filter($eligible))
            ->mapWithKeys(fn (CartItem $item): array => [$this->target($item) => $item->total()])
            ->all();
    }

    /**
     * @param  (callable(CartItem): bool)|null  $eligible
     * @return array<int, DiscountAllocation>
     */
    public function fixedAmountOff(Price $amount, ?callable $eligible = null): array
    {
        return $this->allocator->allocate($amount, $this->eligibleAmounts($eligible));
    }

    /**
     * @param  (callable(CartItem): bool)|null  $eligible
     * @return array<int, DiscountAllocation>
     */
    public function percentageOff(int|string $percentage, ?callable $eligible = null): array
    {
        $eligibleAmounts = $this->eligibleAmounts($eligible);
        $eligibleTotal = array_reduce(
            $eligibleAmounts,
            fn (Price $total, Price $amount): Price => $total->add($amount),
            Price::of(0),
        );

        return $this->allocator->allocate(
            $this->percentageAmount($eligibleTotal, $percentage),
            $eligibleAmounts,
        );
    }

    /** @return array<int, DiscountAllocation> */
    public function fixedAmountOffShipping(Price $amount): array
    {
        return $this->allocator->allocate(
            $amount,
            $this->shippingOption === null ? [] : ['shipping' => $this->shippingOption->price],
        );
    }

    /** @return array<int, DiscountAllocation> */
    public function percentageOffShipping(int|string $percentage): array
    {
        return $this->fixedAmountOffShipping(
            $this->percentageAmount($this->shippingOption->price ?? Price::of(0), $percentage),
        );
    }

    public function shippingTarget(): ?string
    {
        return $this->shippingOption === null ? null : 'shipping';
    }

    private function percentageAmount(Price $amount, int|string $percentage): Price
    {
        $percentage = $this->validatePercentage($percentage);
        $scale = str_contains($percentage, '.')
            ? strlen(substr($percentage, strpos($percentage, '.') + 1))
            : 0;

        return Price::of(bcdiv(
            bcmul($amount->amount(), $percentage, $scale),
            '100',
            0,
        ));
    }

    /** @return numeric-string */
    private function validatePercentage(int|string $percentage): string
    {
        $percentage = (string) $percentage;
        $scale = str_contains($percentage, '.')
            ? strlen(substr($percentage, strpos($percentage, '.') + 1))
            : 0;

        if (! preg_match('/^\d+(?:\.\d+)?$/', $percentage)
            || ! is_numeric($percentage)
            || bccomp($percentage, '100', $scale) === 1) {
            throw new InvalidArgumentException('A discount percentage must be between 0 and 100.');
        }

        return $percentage;
    }
}
