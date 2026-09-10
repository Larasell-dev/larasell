<?php

namespace Larasell\Larasell\Carts;

final readonly class CartMergeLineAdjustment
{
    /**
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(
        public int $variantId,
        public array $metadata,
        public int $requestedQuantity,
        public int $acceptedQuantity,
        public string $reason,
    ) {}
}
