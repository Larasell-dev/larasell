<?php

namespace Larasell\Larasell\Discounts;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Larasell\Larasell\Contracts\CodedPromotion;
use Larasell\Larasell\Contracts\Promotion;
use Larasell\Larasell\Events\PromotionCodeApplied;
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
        $this->codedPromotions();
        $context = $this->context($cart);
        $results = collect();
        $identifiers = [];
        $remaining = $context->eligibleAmounts();

        if ($context->shippingOption !== null) {
            $remaining['shipping'] = $context->shippingOption->price;
        }

        foreach ($this->promotions as $promotionClass) {
            /** @var Promotion $promotion */
            $promotion = $this->container->make($promotionClass);

            if ($promotion instanceof CodedPromotion
                && ! in_array(self::normalizeCode($promotion->code()), $cart->promotionCodes(), true)) {
                continue;
            }

            $result = $promotion->apply($context);

            if ($result === null) {
                continue;
            }

            if (isset($identifiers[$result->identifier])) {
                throw new InvalidArgumentException("Promotion identifiers must be unique. Duplicate identifier [{$result->identifier}].");
            }

            $this->validateTargets($result, $context);
            $identifiers[$result->identifier] = true;

            $allocations = [];

            foreach ($result->allocations as $allocation) {
                $amount = $allocation->amount->greaterThan($remaining[$allocation->target])
                    ? $remaining[$allocation->target]
                    : $allocation->amount;

                if (! $amount->isPositive()) {
                    continue;
                }

                $allocations[] = new DiscountAllocation($allocation->target, $amount);
                $remaining[$allocation->target] = $remaining[$allocation->target]->subtract($amount);
            }

            $results->push(new DiscountResult(
                $result->identifier,
                $result->name,
                $allocations,
                $promotion instanceof CodedPromotion ? self::normalizeCode($promotion->code()) : null,
            ));
        }

        return $results;
    }

    public function attachCode(Cart $cart, string $code): Cart
    {
        $code = self::normalizeCode($code);
        $promotion = $this->codedPromotions()[$code] ?? null;

        if ($promotion === null) {
            throw new InvalidArgumentException("Promotion code [{$code}] is not registered.");
        }

        $context = $this->context($cart);
        $result = $promotion->apply($context);

        if ($result === null || ! $result->total()->isPositive()) {
            throw new InvalidArgumentException("Promotion code [{$code}] is not applicable to this cart.");
        }

        $this->validateTargets($result, $context);

        $codes = $cart->promotionCodes();

        if (! in_array($code, $codes, true)) {
            $codes[] = $code;
            $cart->update(['promotion_codes' => $codes]);
            PromotionCodeApplied::dispatch($cart, $code);
        }

        return $cart;
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    /** @return array<string, CodedPromotion> */
    private function codedPromotions(): array
    {
        $coded = [];

        foreach ($this->promotions as $promotionClass) {
            /** @var Promotion $promotion */
            $promotion = $this->container->make($promotionClass);

            if (! $promotion instanceof CodedPromotion) {
                continue;
            }

            $code = self::normalizeCode($promotion->code());

            if ($code === '') {
                throw new InvalidArgumentException("Coded promotion [{$promotionClass}] must provide a code.");
            }

            if (isset($coded[$code])) {
                throw new InvalidArgumentException("Promotion codes must be unique. Duplicate code [{$code}].");
            }

            $coded[$code] = $promotion;
        }

        return $coded;
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
