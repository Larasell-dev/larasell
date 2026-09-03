<?php

namespace Larasell\Larasell\Cart\Exceptions;

abstract class CartQuantityException extends CartException
{
    public function __construct(
        string $message,
        public readonly int $requestedQuantity,
    ) {
        parent::__construct($message);
    }
}
