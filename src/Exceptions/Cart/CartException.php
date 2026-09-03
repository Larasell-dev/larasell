<?php

namespace Larasell\Larasell\Exceptions\Cart;

use DomainException;

abstract class CartException extends DomainException
{
    abstract public function reason(): string;

    /** @return array<string, mixed> */
    abstract public function context(): array;
}
