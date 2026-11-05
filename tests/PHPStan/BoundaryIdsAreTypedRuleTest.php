<?php

namespace Larasell\Larasell\Tests\PHPStan;

use Larasell\Larasell\PHPStan\Rules\BoundaryIdsAreTypedRule;
use PHPStan\Rules\Rule;

/** @extends RuleTestCase<BoundaryIdsAreTypedRule> */
final class BoundaryIdsAreTypedRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BoundaryIdsAreTypedRule;
    }

    public function test_it_reports_bare_ids_in_public_boundary_signatures(): void
    {
        $this->analyse([__DIR__.'/Fixtures/BoundaryIds.php'], [
            ['The parameter $productId of Larasell\Larasell\Catalog\Actions\TakesBareIds::handle() is a bare ID: type it, e.g. ProductId or list<ProductId>, so an ID of another kind cannot be passed.', 8],
            ['The parameter $categoryIds of Larasell\Larasell\Catalog\Actions\TakesBareIds::handle() is a bare ID: type it, e.g. ProductId or list<ProductId>, so an ID of another kind cannot be passed.', 8],
            ['The parameter $id of Larasell\Larasell\Catalog\Actions\TakesBareIds::handle() is a bare ID: type it, e.g. ProductId or list<ProductId>, so an ID of another kind cannot be passed.', 8],
            ['The parameter $productId of Larasell\Larasell\Catalog\Events\CarriesBareId::__construct() is a bare ID: type it, e.g. ProductId or list<ProductId>, so an ID of another kind cannot be passed.', 27],
            ['The property $variantId of Larasell\Larasell\Catalog\Events\CarriesBareId is a bare ID: type it, e.g. ProductId or list<ProductId>, so an ID of another kind cannot be passed.', 27],
        ]);
    }
}
