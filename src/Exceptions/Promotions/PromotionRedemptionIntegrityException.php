<?php

namespace Larasell\Larasell\Exceptions\Promotions;

use RuntimeException;

final class PromotionRedemptionIntegrityException extends RuntimeException
{
    public function __construct(public readonly string $identifier)
    {
        parent::__construct("Promotion [{$identifier}] has inconsistent redemption capacity.");
    }
}
