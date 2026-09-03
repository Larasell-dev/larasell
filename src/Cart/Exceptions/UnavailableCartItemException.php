<?php

namespace Larasell\Larasell\Cart\Exceptions;

use Larasell\Larasell\Models\ProductVariant;

final class UnavailableCartItemException extends CartAvailabilityException
{
    public function __construct(
        public readonly ProductVariant $variant,
    ) {
        parent::__construct('The product variant is unavailable.');
    }

    public function reason(): string
    {
        return 'unavailable_cart_item';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'product_id' => $this->variant->product_id,
            'variant_id' => $this->variant->getKey(),
        ];
    }
}
