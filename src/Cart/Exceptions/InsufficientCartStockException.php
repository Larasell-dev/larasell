<?php

namespace Larasell\Larasell\Cart\Exceptions;

use Larasell\Larasell\Models\ProductVariant;

final class InsufficientCartStockException extends CartAvailabilityException
{
    public function __construct(
        public readonly ProductVariant $variant,
        public readonly int $requestedQuantity,
        public readonly int $availableStock,
    ) {
        $subject = $variant->is_default ? 'product' : 'variant';

        parent::__construct("Cart item quantity exceeds available {$subject} stock.");
    }

    public function reason(): string
    {
        return 'insufficient_stock';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'product_id' => $this->variant->product_id,
            'variant_id' => $this->variant->getKey(),
            'requested_quantity' => $this->requestedQuantity,
            'available_stock' => $this->availableStock,
        ];
    }
}
