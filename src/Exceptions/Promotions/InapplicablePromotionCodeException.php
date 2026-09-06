<?php

namespace Larasell\Larasell\Exceptions\Promotions;

final class InapplicablePromotionCodeException extends PromotionCodeException
{
    public function __construct(string $code)
    {
        parent::__construct("Promotion code [{$code}] is not applicable to this cart.", $code);
    }

    public function reason(): string
    {
        return 'inapplicable_promotion_code';
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
