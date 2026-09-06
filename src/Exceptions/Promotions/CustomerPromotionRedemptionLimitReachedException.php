<?php

namespace Larasell\Larasell\Exceptions\Promotions;

final class CustomerPromotionRedemptionLimitReachedException extends PromotionRedemptionException
{
    public function __construct(
        string $identifier,
        public readonly string $customerIdentifier,
    ) {
        parent::__construct("Promotion [{$identifier}] has reached its customer redemption limit.", $identifier);
    }

    public function reason(): string
    {
        return 'customer_redemption_limit_reached';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'identifier' => $this->identifier,
            'customer_identifier' => $this->customerIdentifier,
        ];
    }
}
