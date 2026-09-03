<?php

namespace Larasell\Larasell\Cart\Exceptions;

final class InvalidCartQuantityException extends CartQuantityException
{
    public function __construct(int $requestedQuantity)
    {
        parent::__construct('Cart item quantity must be at least 1.', $requestedQuantity);
    }

    public function reason(): string
    {
        return 'invalid_quantity';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'requested_quantity' => $this->requestedQuantity,
            'minimum_quantity' => 1,
        ];
    }
}
