<?php

namespace Larasell\Larasell\Discounts;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
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

    public function shippingTarget(): ?string
    {
        return $this->shippingOption === null ? null : 'shipping';
    }
}
