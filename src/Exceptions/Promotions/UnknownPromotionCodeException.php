<?php

namespace Larasell\Larasell\Exceptions\Promotions;

final class UnknownPromotionCodeException extends PromotionCodeException
{
    public function __construct(string $code)
    {
        parent::__construct("Promotion code [{$code}] is not registered.", $code);
    }

    public function reason(): string
    {
        return 'unknown_promotion_code';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'code' => $this->promotionCode,
        ];
    }
}
