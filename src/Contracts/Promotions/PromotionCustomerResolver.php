<?php

namespace Larasell\Larasell\Contracts\Promotions;

interface PromotionCustomerResolver
{
    public function resolve(?int $customerId, string $email): string;
}
