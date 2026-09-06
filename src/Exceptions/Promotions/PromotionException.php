<?php

namespace Larasell\Larasell\Exceptions\Promotions;

use DomainException;

abstract class PromotionException extends DomainException
{
    abstract public function reason(): string;

    /** @return array<string, mixed> */
    abstract public function context(): array;
}
