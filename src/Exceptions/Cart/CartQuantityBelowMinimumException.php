<?php

namespace Larasell\Larasell\Exceptions\Cart;

use Larasell\Larasell\Models\ProductVariant;

final class CartQuantityBelowMinimumException extends CartQuantityException
{
    public function __construct(
        public readonly ProductVariant $variant,
        int $requestedQuantity,
        public readonly int $minimumQuantity,
    ) {
        $subject = $variant->is_default ? 'product' : 'variant';

        parent::__construct(
            "Cart item quantity is below the {$subject} minimum quantity.",
            $requestedQuantity,
        );
    }

    public function reason(): string
    {
        return 'quantity_below_minimum';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'product_id' => $this->variant->product_id,
            'variant_id' => $this->variant->getKey(),
            'requested_quantity' => $this->requestedQuantity,
            'minimum_quantity' => $this->minimumQuantity,
        ];
    }
}
