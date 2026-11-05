<?php

namespace Larasell\Larasell\Carts;

use Larasell\Larasell\Catalog\VariantId;

final readonly class CartMergeLineAdjustment
{
    /**
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(
        public VariantId $variantId,
        public array $metadata,
        public int $requestedQuantity,
        public int $acceptedQuantity,
        public string $reason,
    ) {}
}
