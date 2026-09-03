<?php

namespace Larasell\Larasell\Exceptions\Cart;

use Larasell\Larasell\Models\ProductVariant;

final class CartQuantityExceedsMaximumException extends CartQuantityException
{
    public function __construct(
        public readonly ProductVariant $variant,
        int $requestedQuantity,
        public readonly int $maximumQuantity,
    ) {
        $subject = $variant->is_default ? 'product' : 'variant';

        parent::__construct(
            "Cart item quantity exceeds the {$subject} maximum quantity.",
            $requestedQuantity,
        );
    }

    public function reason(): string
    {
        return 'quantity_exceeds_maximum';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'product_id' => $this->variant->product_id,
            'variant_id' => $this->variant->getKey(),
            'requested_quantity' => $this->requestedQuantity,
            'maximum_quantity' => $this->maximumQuantity,
        ];
    }
}
