<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Larasell\Larasell\Tests\ConcurrencyTestCase;
use Larasell\Larasell\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

pest()->extend(ConcurrencyTestCase::class)
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
| Catalog writes the way apps make them: through the catalog's actions, which keep SEO URLs in step.
*/

use Larasell\Larasell\Catalog\Actions\DeleteCategory;
use Larasell\Larasell\Catalog\Actions\DeleteProduct;
use Larasell\Larasell\Catalog\Actions\MoveCategory;
use Larasell\Larasell\Catalog\Actions\UpdateCategory;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;

/** @param  array<string, mixed>  $attributes */
function updateCatalog(Product|Category $model, array $attributes): void
{
    if ($model instanceof Product) {
        app(UpdateProduct::class)->handle($model->id, $attributes);

        return;
    }

    if (array_key_exists('parent_id', $attributes)) {
        app(MoveCategory::class)->handle($model->id, $attributes['parent_id']);
        unset($attributes['parent_id']);
    }

    if ($attributes !== []) {
        app(UpdateCategory::class)->handle($model->id, $attributes);
    }
}

function deleteFromCatalog(Product|Category $model): void
{
    $model instanceof Product
        ? app(DeleteProduct::class)->handle($model->id)
        : app(DeleteCategory::class)->handle($model->id);
}
