<?php

namespace Larasell\Larasell\Enums;

/**
 * A Larasell license plan. The values match the `plan` claim of the leases larasell.dev signs.
 */
enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';

    /** Whether this plan grants what the given plan grants: Pro includes everything in Free. */
    public function includes(self $plan): bool
    {
        return $this->rank() >= $plan->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Pro => 1,
        };
    }
}
