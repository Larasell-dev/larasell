<?php

namespace Larasell\Larasell\Exceptions\Promotions;

abstract class PromotionCodeException extends PromotionException
{
    public function __construct(
        string $message,
        public readonly string $promotionCode,
    ) {
        parent::__construct($message);
    }
}
