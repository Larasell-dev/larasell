<?php

namespace Larasell\Larasell\Exceptions\Cart;

abstract class CartQuantityException extends CartException
{
    public function __construct(
        string $message,
        public readonly int $requestedQuantity,
    ) {
        parent::__construct($message);
    }
}
