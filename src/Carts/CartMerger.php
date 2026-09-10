<?php

namespace Larasell\Larasell\Carts;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Carts\Strategies\CallbackCartMergeStrategy;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Events\CartMerged;
use Larasell\Larasell\Exceptions\Cart\CartMergeException;
use Larasell\Larasell\Exceptions\Promotions\PromotionException;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Models\ProductVariant;

final readonly class CartMerger
{
    public function __construct(
        private ConnectionInterface $database,
        private PromotionManager $promotions,
        private CartMergeStrategy $defaultStrategy,
    ) {}

    public function merge(Cart $destination, Cart $source, CartMergeStrategy|callable|null $strategy = null): CartMergeResult
    {
        if ($destination->is($source)) {
            throw new CartMergeException('same_cart', 'A cart cannot be merged into itself.', [
                'cart_id' => $destination->getKey(),
            ]);
        }

        $strategy = $this->resolveStrategy($strategy);

        return $this->database->transaction(function () use ($destination, $source, $strategy): CartMergeResult {
            /** @var Cart $lockedDestination */
            $lockedDestination = $destination->newQuery()
                ->lockForUpdate()
                ->findOrFail($destination->getKey());

            /** @var Cart|null $lockedSource */
            $lockedSource = $source->newQuery()
                ->lockForUpdate()
                ->find($source->getKey());

            if ($lockedSource === null) {
                return new CartMergeResult($lockedDestination->fresh() ?? $lockedDestination);
            }

            $this->assertMergeable($lockedDestination, $lockedSource);
            $this->loadLockedItems($lockedDestination);
            $this->loadLockedItems($lockedSource);

            $context = new CartMergeContext($lockedDestination, $lockedSource);
            $linePlan = $strategy->lines($context);
            $attributePlan = $strategy->attributes($context);
            $skipped = [];
            $adjusted = [];

            if ($linePlan->replaceDestinationItems) {
                $lockedDestination->clear();
                $this->loadLockedItems($lockedDestination);
            }

            foreach ($linePlan->intents as $intent) {
                $variant = $this->lockedVariant($intent->variant);

                if ($variant === null) {
                    $skipped[] = $this->adjustment($intent, 0, 'invalid_cart_item');

                    continue;
                }

                if ($linePlan->failOnConflict) {
                    $lockedDestination->set($variant, $intent->quantity, $intent->metadata);
                    $this->loadLockedItems($lockedDestination);

                    continue;
                }

                [$acceptedQuantity, $reason] = $this->acceptedQuantity($lockedDestination, $intent, $variant);

                if ($acceptedQuantity < 1) {
                    $skipped[] = $this->adjustment($intent, 0, $reason ?? 'invalid_cart_quantity');

                    continue;
                }

                if ($acceptedQuantity !== $intent->quantity) {
                    $adjusted[] = $this->adjustment($intent, $acceptedQuantity, $reason ?? 'quantity_adjusted');
                }

                $lockedDestination->set($variant, $acceptedQuantity, $intent->metadata);
                $this->loadLockedItems($lockedDestination);
            }

            $droppedPromotionCodes = $this->applyAttributes($lockedDestination, $attributePlan);
            $sourceCartId = (int) $lockedSource->getKey();

            $lockedSource->delete();

            /** @var Cart $merged */
            $merged = $lockedDestination->fresh(['items']) ?? $lockedDestination;

            CartMerged::dispatch(
                $merged,
                $sourceCartId,
                $strategy::class,
                $skipped,
                $adjusted,
                $droppedPromotionCodes,
            );

            return new CartMergeResult($merged, $skipped, $adjusted, $droppedPromotionCodes);
        });
    }

    private function resolveStrategy(CartMergeStrategy|callable|null $strategy): CartMergeStrategy
    {
        if ($strategy instanceof CartMergeStrategy) {
            return $strategy;
        }

        if ($strategy !== null) {
            return new CallbackCartMergeStrategy(Closure::fromCallable($strategy));
        }

        return $this->defaultStrategy;
    }

    private function assertMergeable(Cart $destination, Cart $source): void
    {
        if ($destination->currency !== $source->currency) {
            throw new CartMergeException('currency_mismatch', 'Carts with different currencies cannot be merged.', [
                'destination_cart_id' => $destination->getKey(),
                'destination_currency' => $destination->currency->value,
                'source_cart_id' => $source->getKey(),
                'source_currency' => $source->currency->value,
            ]);
        }

        if ($destination->user_id !== null && $source->user_id !== null && $destination->user_id !== $source->user_id) {
            throw new CartMergeException('cart_owned_by_another_user', 'A cart owned by another user cannot be merged.', [
                'destination_cart_id' => $destination->getKey(),
                'destination_user_id' => $destination->user_id,
                'source_cart_id' => $source->getKey(),
                'source_user_id' => $source->user_id,
            ]);
        }
    }

    private function loadLockedItems(Cart $cart): void
    {
        $cart->unsetRelation('items');
        $cart->setRelation('items', $cart->items()
            ->with(['variant.product'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get());
    }

    private function lockedVariant(ProductVariant $variant): ?ProductVariant
    {
        /** @var ProductVariant|null $locked */
        $locked = $variant->newQuery()
            ->with('product')
            ->lockForUpdate()
            ->find($variant->getKey());

        return $locked;
    }

    /**
     * @return array{0: int, 1: string|null}
     */
    private function acceptedQuantity(Cart $destination, CartMergeLineIntent $intent, ProductVariant $variant): array
    {
        if ($variant->status !== Visibility::Visible) {
            return [0, 'unavailable_cart_item'];
        }

        $metadata = CartMergeContext::normalizeMetadata($intent->metadata);
        $otherQuantity = $destination->items
            ->filter(fn (CartItem $item): bool => (int) $item->product_variant_id === (int) $variant->getKey()
                && CartMergeContext::normalizeMetadata($item->metadata->all()) !== $metadata)
            ->sum('quantity');

        $maximum = $variant->maximumQuantity();
        $stock = $variant->allowsBackorders() ? null : $variant->availableStock();
        $limit = min(array_filter([$maximum, $stock], fn (?int $value): bool => $value !== null) ?: [PHP_INT_MAX]);
        $accepted = min($intent->quantity, max(0, $limit - (int) $otherQuantity));

        $reason = null;

        if ($maximum !== null && $intent->quantity + (int) $otherQuantity > $maximum) {
            $reason = 'quantity_exceeds_maximum';
        }

        if ($stock !== null
            && $intent->quantity + (int) $otherQuantity > $stock
            && ($reason === null || $stock <= $limit)) {
            $reason = 'insufficient_stock';
        }

        $minimum = $variant->minimumQuantity();

        if ($accepted > 0 && $minimum !== null && $accepted < $minimum) {
            return [0, 'quantity_below_minimum'];
        }

        return [$accepted, $reason];
    }

    /**
     * @return list<string>
     */
    private function applyAttributes(Cart $cart, CartMergeAttributePlan $plan): array
    {
        $cart->forceFill([
            'metadata' => $plan->metadata,
            'promotion_codes' => [],
            'shipping_option' => null,
        ])->save();

        $droppedCodes = [];

        foreach ($plan->promotionCodes as $code) {
            try {
                $this->promotions->attachCode($cart, $code);
            } catch (PromotionException) {
                $droppedCodes[] = $code;
            }
        }

        $cart->forceFill([
            'shipping_option' => $this->availableShippingOption($cart, [
                $plan->preferredShippingOption,
                $plan->fallbackShippingOption,
            ]),
        ])->save();

        return $droppedCodes;
    }

    /**
     * @param  array<int, string|null>  $handles
     */
    private function availableShippingOption(Cart $cart, array $handles): ?string
    {
        foreach ($handles as $handle) {
            if ($handle !== null && $cart->shippingOptions()->firstWhere('handle', $handle) !== null) {
                return $handle;
            }
        }

        return null;
    }

    private function adjustment(CartMergeLineIntent $intent, int $acceptedQuantity, string $reason): CartMergeLineAdjustment
    {
        return new CartMergeLineAdjustment(
            variantId: (int) $intent->variant->getKey(),
            metadata: CartMergeContext::normalizeMetadata($intent->metadata),
            requestedQuantity: $intent->quantity,
            acceptedQuantity: $acceptedQuantity,
            reason: $reason,
        );
    }
}
