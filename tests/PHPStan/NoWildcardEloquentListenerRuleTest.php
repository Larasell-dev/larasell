<?php

namespace Larasell\Larasell\Tests\PHPStan;

use Larasell\Larasell\PHPStan\Rules\NoWildcardEloquentListenerRule;
use PHPStan\Rules\Rule;

/** @extends RuleTestCase<NoWildcardEloquentListenerRule> */
final class NoWildcardEloquentListenerRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoWildcardEloquentListenerRule;
    }

    public function test_it_reports_listeners_for_every_model(): void
    {
        $this->analyse([__DIR__.'/Fixtures/WildcardListener.php'], [
            ["Do not listen to [eloquent.saved: *]: react to the owning module's domain events instead.", 14],
            ["Do not listen to [eloquent.deleting: *]: react to the owning module's domain events instead.", 17],
            ["Do not listen to [eloquent.saved: *]: react to the owning module's domain events instead.", 17],
        ]);
    }
}
