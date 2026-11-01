<?php

namespace Larasell\Larasell\Licensing;

use Larasell\Larasell\Contracts\PlanResolver;
use Larasell\Larasell\Enums\Plan;

/**
 * The plan set with LARASELL_PLAN, Pro by default. For development and staging, which the
 * license agreement lets run without a license; production never uses it.
 */
final class EnvPlanResolver implements PlanResolver
{
    public function plan(): Plan
    {
        return Plan::from(config('larasell.licensing.plan', Plan::Pro->value));
    }
}
