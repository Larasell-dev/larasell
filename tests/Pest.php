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

/*
| Sales channels: tests run as if in a storefront request of the default channel, unless they say otherwise.
*/

use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\Actions\UpdateSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\Storefront\Actions\AddEntryPoint;
use Larasell\Larasell\Storefront\Actions\DeleteEntryPoint;
use Larasell\Larasell\Storefront\Models\EntryPoint;
use Larasell\Larasell\Storefront\StorefrontContext;

uses()->beforeEach(function () {
    StorefrontContext::for('default', Locale::ENGLISH)->activate();
})->in('Feature');

/**
 * Makes the default channel serve these locales, as installing with them would: one locale on app.url,
 * several each below its own prefix, unless entry points are given (the first one's locale becomes the default), e.g. [['url' => 'http://localhost', 'locale' => 'de']].
 *
 * @param  list<string>  $locales
 * @param  list<array{url: string, locale: string}>|null  $entryPoints
 */
function shopLocales(array $locales, ?array $entryPoints = null): void
{
    $channel = SalesChannel::query()->where('key', 'default')->sole();
    $url = rtrim((string) config('app.url'), '/');
    $entryPoints ??= count($locales) === 1
        ? [['url' => $url, 'locale' => $locales[0]]]
        : array_map(fn (string $locale): array => ['url' => $url.'/'.strtolower($locale), 'locale' => $locale], $locales);
    $old = array_map(fn (Locale $locale): string => $locale->value, $channel->locales());

    // Keeps the old locales until the new entry points exist, as every locale needs one.
    $all = array_values(array_unique([...$old, ...$locales]));
    config()->set('larasell.locale.supported', $all);
    app(UpdateSalesChannel::class)->handle($channel->id, ['locales' => $all]);

    foreach (EntryPoint::query()->where('sales_channel_id', $channel->id)->pluck('id') as $id) {
        app(DeleteEntryPoint::class)->handle((int) $id);
    }

    foreach ($entryPoints as $entryPoint) {
        app(AddEntryPoint::class)->handle($channel->id, $entryPoint);
    }

    config()->set('larasell.locale.supported', $locales);
    app(UpdateSalesChannel::class)->handle($channel->id, ['locales' => $locales, 'default_locale' => $entryPoints[0]['locale']]);

    StorefrontContext::for('default', $entryPoints[0]['locale'])->activate();
}
