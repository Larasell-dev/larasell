<?php

namespace Larasell\Larasell\Carts;

use Larasell\Larasell\Models\ProductVariant;

final readonly class CartMergeLineIntent
{
    /**
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(
        public ProductVariant $variant,
        public int $quantity,
        public array $metadata = [],
    ) {}
}
