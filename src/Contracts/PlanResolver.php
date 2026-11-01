<?php

namespace Larasell\Larasell\Contracts;

use Larasell\Larasell\Enums\Plan;

/**
 * Decides which plan this installation is on. Production always uses the lease larasell.dev
 * signed; other environments read the plan from LARASELL_PLAN unless they opt into leases.
 */
interface PlanResolver
{
    public function plan(): Plan;
}
