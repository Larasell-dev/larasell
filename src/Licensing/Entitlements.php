<?php

namespace Larasell\Larasell\Licensing;

use Larasell\Larasell\Contracts\PlanResolver;
use Larasell\Larasell\Enums\Plan;
use Larasell\Larasell\Exceptions\Licensing\UnknownEntitlement;

/**
 * What this installation's plan entitles it to. Packages define their entitlements with the
 * plan that grants them and check them where the feature lives:
 *
 *     Entitlement::define('admin.audit-log-search', Plan::Pro);
 *     Entitlement::active('admin.audit-log-search');
 *
 * The plan comes from the PlanResolver: the lease in production, LARASELL_PLAN elsewhere.
 */
final class Entitlements
{
    /** @var array<string, Plan> */
    private array $definitions = [];

    private ?Plan $fakePlan = null;

    public function define(string $name, Plan $plan): void
    {
        $this->definitions[$name] = $plan;
    }

    /**
     * @throws UnknownEntitlement
     */
    public function active(string $name): bool
    {
        $plan = $this->definitions[$name] ?? throw UnknownEntitlement::undefined($name);

        return $this->plan()->includes($plan);
    }

    public function inactive(string $name): bool
    {
        return ! $this->active($name);
    }

    /**
     * Every defined entitlement and whether it is active, e.g. to hand to a frontend.
     *
     * @return array<string, bool>
     */
    public function all(): array
    {
        $plan = $this->plan();

        return array_map(fn (Plan $required): bool => $plan->includes($required), $this->definitions);
    }

    public function plan(): Plan
    {
        // Resolved on every call, as the driver depends on the environment.
        return $this->fakePlan ?? app(PlanResolver::class)->plan();
    }

    /** Pretends the installation has the plan, e.g. to test what a Free shop sees. */
    public function fake(Plan $plan): void
    {
        $this->fakePlan = $plan;
    }
}
