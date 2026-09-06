<?php

namespace Larasell\Larasell\Exceptions\Promotions;

abstract class PromotionRedemptionException extends PromotionException
{
    public function __construct(
        string $message,
        public readonly string $identifier,
    ) {
        parent::__construct($message);
    }
}
