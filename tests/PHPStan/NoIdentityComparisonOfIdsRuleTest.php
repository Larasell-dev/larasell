<?php

namespace Larasell\Larasell\Tests\PHPStan;

use Larasell\Larasell\PHPStan\Rules\NoIdentityComparisonOfIdsRule;
use PHPStan\Rules\Rule;

/** @extends RuleTestCase<NoIdentityComparisonOfIdsRule> */
final class NoIdentityComparisonOfIdsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoIdentityComparisonOfIdsRule;
    }

    public function test_it_reports_identity_comparisons_of_ids_but_not_null_checks(): void
    {
        $this->analyse([__DIR__.'/Fixtures/IdComparisons.php'], [
            ['Comparing IDs with === compares instances, so two equal IDs never match: use equals() or compare their ->value.', 10],
            ['Comparing IDs with !== compares instances, so two equal IDs never match: use equals() or compare their ->value.', 11],
        ]);
    }
}
