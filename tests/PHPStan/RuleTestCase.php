<?php

namespace Larasell\Larasell\Tests\PHPStan;

use Illuminate\Console\Application;
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

    /**
     * Larastan boots its own Laravel app, which registers console bootstrappers such as Tinker's.
     * They are static, so they would run in the feature tests that follow in the same process.
     */
    public static function tearDownAfterClass(): void
    {
        Application::forgetBootstrappers();

        parent::tearDownAfterClass();
    }

    protected static function modules(): ModuleMap
    {
        return new ModuleMap([
            'Catalog' => ['Larasell\Larasell\Catalog\\'],
            'Seo' => ['Larasell\Larasell\Seo\\'],
        ]);
    }
}
