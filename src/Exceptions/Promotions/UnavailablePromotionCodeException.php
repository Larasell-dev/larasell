<?php

namespace Larasell\Larasell\Exceptions\Promotions;

use Carbon\CarbonInterface;

final class UnavailablePromotionCodeException extends PromotionCodeException
{
    public function __construct(
        string $code,
        public readonly ?CarbonInterface $startsAt = null,
        public readonly ?CarbonInterface $endsAt = null,
    ) {
        parent::__construct("Promotion code [{$code}] is not currently available.", $code);
    }

    public function reason(): string
    {
        return 'unavailable_promotion_code';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'code' => $this->promotionCode,
            'starts_at' => $this->startsAt?->toIso8601String(),
            'ends_at' => $this->endsAt?->toIso8601String(),
        ];
    }
}
