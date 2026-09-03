<?php

namespace Larasell\Larasell\Cart\Exceptions;

use DomainException;

abstract class CartException extends DomainException
{
    abstract public function reason(): string;

    /** @return array<string, mixed> */
    abstract public function context(): array;
}
