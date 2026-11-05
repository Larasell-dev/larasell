<?php

namespace Larasell\Larasell\Tests\PHPStan;

use Larasell\Larasell\PHPStan\Rules\DomainEventsCarryNoModelsRule;
use PHPStan\Rules\Rule;

/** @extends RuleTestCase<DomainEventsCarryNoModelsRule> */
final class DomainEventsCarryNoModelsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new DomainEventsCarryNoModelsRule($this->createReflectionProvider());
    }

    public function test_it_reports_models_and_collections_in_domain_events(): void
    {
        $this->analyse([__DIR__.'/Fixtures/DomainEvents.php', __DIR__.'/Fixtures/NotAnEvent.php'], [
            ['The domain event Larasell\Larasell\Catalog\Events\CarriesModels carries [Larasell\Larasell\Catalog\Models\Product] in $product: carry its ID and the facts listeners need instead.', 11],
            ['The domain event Larasell\Larasell\Catalog\Events\CarriesModels carries [Illuminate\Database\Eloquent\Collection] in $categories: carry its ID and the facts listeners need instead.', 11],
        ]);
    }
}
