<?php

namespace Larasell\Larasell\Promotions;

use Larasell\Larasell\Contracts\Promotions\PromotionCustomerResolver;

final class DefaultPromotionCustomerResolver implements PromotionCustomerResolver
{
    public function resolve(?int $customerId, string $email): string
    {
        return $customerId === null
            ? 'email:'.strtolower(trim($email))
            : 'customer:'.$customerId;
    }
}
