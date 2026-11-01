<?php

namespace Larasell\Larasell\Facades;

use Illuminate\Support\Facades\Facade;
use Larasell\Larasell\Enums\Plan;
use Larasell\Larasell\Licensing\Entitlements;

/**
 * @method static void define(string $name, Plan $plan)
 * @method static bool active(string $name)
 * @method static bool inactive(string $name)
 * @method static array<string, bool> all()
 * @method static Plan plan()
 * @method static void fake(Plan $plan)
 *
 * @see Entitlements
 */
class Entitlement extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Entitlements::class;
    }
}
