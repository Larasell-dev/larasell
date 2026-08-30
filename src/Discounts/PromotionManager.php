<?php

namespace Larasell\Larasell\Discounts;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Larasell\Larasell\Contracts\Promotion;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Price;

final class PromotionManager
{
    /** @var array<int, class-string<Promotion>> */
    private array $promotions = [];

    public function __construct(
        private readonly Container $container,
        private readonly ProportionalDiscountAllocator $allocator,
    ) {}

    /** @param class-string<Promotion> $promotion */
    public function register(string $promotion): void
    {
        if (! is_subclass_of($promotion, Promotion::class)) {
            throw new InvalidArgumentException("Promotion [{$promotion}] must implement ".Promotion::class.'.');
        }

        if (! in_array($promotion, $this->promotions, true)) {
            $this->promotions[] = $promotion;
        }
    }

    /** @return Collection<int, DiscountResult> */
    public function apply(Cart $cart): Collection
    {
        $context = $this->context($cart);
        $results = collect();
        $identifiers = [];

        foreach ($this->promotions as $promotionClass) {
            $result = $this->container->make($promotionClass)->apply($context);

            if ($result === null) {
                continue;
            }

            if (isset($identifiers[$result->identifier])) {
                throw new InvalidArgumentException("Promotion identifiers must be unique. Duplicate identifier [{$result->identifier}].");
            }

            $this->validateTargets($result, $context);
            $identifiers[$result->identifier] = true;
            $results->push($result);
        }

        return $results;
    }

    private function context(Cart $cart): PromotionContext
    {
        /** @var Collection<int, CartItem> $items */
        $items = $cart->items()
            ->with('product')
            ->orderBy('id')
            ->get();
        $subtotal = $items->reduce(
            fn (Price $total, CartItem $item): Price => $total->add($item->total()),
            Price::of(0),
        );

        return new PromotionContext(
            cart: $cart,
            items: $items,
            currency: $cart->currency,
            subtotal: $subtotal,
            shippingOption: $cart->shippingOption(),
            allocator: $this->allocator,
        );
    }

    private function validateTargets(DiscountResult $result, PromotionContext $context): void
    {
        $validTargets = array_fill_keys(array_keys($context->eligibleAmounts()), true);

        if ($context->shippingTarget() !== null) {
            $validTargets[$context->shippingTarget()] = true;
        }

        foreach ($result->allocations as $allocation) {
            if (! isset($validTargets[$allocation->target])) {
                throw new InvalidArgumentException(
                    "Promotion [{$result->identifier}] returned an invalid allocation target [{$allocation->target}]."
                );
            }
        }
    }
}
