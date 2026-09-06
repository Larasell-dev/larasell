<?php

namespace Larasell\Larasell\Exceptions\Promotions;

final class PromotionRedemptionLimitReachedException extends PromotionRedemptionException
{
    public function __construct(string $identifier)
    {
        parent::__construct("Promotion [{$identifier}] has reached its redemption limit.", $identifier);
    }

    public function reason(): string
    {
        return 'redemption_limit_reached';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'identifier' => $this->identifier,
        ];
    }
}
