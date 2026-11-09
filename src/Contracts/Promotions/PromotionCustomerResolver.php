<?php

namespace Larasell\Larasell\Contracts\Promotions;

interface PromotionCustomerResolver
{
    public function resolve(string $email): string;
}
