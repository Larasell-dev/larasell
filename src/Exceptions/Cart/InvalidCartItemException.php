<?php

namespace Larasell\Larasell\Exceptions\Cart;

use Larasell\Larasell\Models\ProductVariant;

final class InvalidCartItemException extends CartException
{
    public function __construct(
        public readonly ?ProductVariant $variant = null,
    ) {
        parent::__construct($variant === null
            ? 'The cart item product variant is invalid.'
            : 'The product variant is not persisted or has no product.');
    }

    public function reason(): string
    {
        return 'invalid_cart_item';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'product_id' => $this->variant?->product_id,
            'variant_id' => $this->variant?->getKey(),
        ];
    }
}
