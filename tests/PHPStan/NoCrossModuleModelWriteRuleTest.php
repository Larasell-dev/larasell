<?php

namespace Larasell\Larasell\Tests\PHPStan;

use Larasell\Larasell\PHPStan\Rules\NoCrossModuleModelWriteRule;
use PHPStan\Rules\Rule;

/** @extends RuleTestCase<NoCrossModuleModelWriteRule> */
final class NoCrossModuleModelWriteRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoCrossModuleModelWriteRule(self::modules(), $this->createReflectionProvider());
    }

    public function test_it_reports_writes_to_another_modules_models_only(): void
    {
        $message = fn (string $model, string $method): string => "Seo must not write [Larasell\\Larasell\\Catalog\\Models\\{$model}] of module Catalog with {$method}(): call one of Catalog's actions.";

        $this->analyse([__DIR__.'/Fixtures/CrossModuleWrites.php'], [
            [$message('Product', 'update'), 13],
            [$message('Product', 'save'), 14],
            [$message('Product', 'create'), 15],
            [$message('Product', 'update'), 16],
            [$message('Category', 'create'), 17],
            [$message('Category', 'attach'), 18],
        ]);
    }
}
