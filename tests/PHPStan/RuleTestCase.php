<?php

namespace Larasell\Larasell\Tests\PHPStan;

use Larasell\Larasell\PHPStan\ModuleMap;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase as BaseRuleTestCase;

/**
 * @template TRule of Rule
 *
 * @extends BaseRuleTestCase<TRule>
 */
abstract class RuleTestCase extends BaseRuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/../../vendor/larastan/larastan/extension.neon'];
    }

    protected static function modules(): ModuleMap
    {
        return new ModuleMap([
            'Catalog' => ['Larasell\Larasell\Catalog\\', 'Larasell\Larasell\Models\Category', 'Larasell\Larasell\Models\Product'],
            'Seo' => ['Larasell\Larasell\Seo\\'],
        ]);
    }
}
