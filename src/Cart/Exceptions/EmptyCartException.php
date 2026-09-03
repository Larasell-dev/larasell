<?php

namespace Larasell\Larasell\Cart\Exceptions;

final class EmptyCartException extends CartCheckoutException
{
    public function __construct()
    {
        parent::__construct('Cannot checkout an empty cart.');
    }

    public function reason(): string
    {
        return 'empty_cart';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['reason' => $this->reason()];
    }
}
