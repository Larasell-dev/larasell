<?php

namespace Larasell\Larasell\Licensing;

use Larasell\Larasell\Contracts\PlanResolver;
use Larasell\Larasell\Enums\Plan;

/**
 * The plan of the current lease, or Free without a valid one.
 */
final class LeasePlanResolver implements PlanResolver
{
    public function __construct(private readonly License $license) {}

    public function plan(): Plan
    {
        return $this->license->lease()->plan ?? Plan::Free;
    }
}
