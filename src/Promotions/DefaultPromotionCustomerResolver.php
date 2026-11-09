<?php

namespace Larasell\Larasell\Promotions;

use Larasell\Larasell\Contracts\Promotions\PromotionCustomerResolver;

final class DefaultPromotionCustomerResolver implements PromotionCustomerResolver
{
    public function resolve(string $email): string
    {
        return 'email:'.strtolower(trim($email));
    }
}
