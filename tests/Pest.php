<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Larasell\Larasell\Catalog\Actions\MoveCategoryPayload;
use Larasell\Larasell\Catalog\Actions\UpdateCategoryPayload;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductVariant;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinitionValue;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\Storefront\EntryPointId;
use Larasell\Larasell\Tests\ConcurrencyTestCase;
use Larasell\Larasell\Tests\TestCase;
use Larasell\Larasell\Translatable;

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
use Larasell\Larasell\Catalog\Actions\UpdateProductPayload;
use Larasell\Larasell\Catalog\Models\Category;

/** @param  array<string, mixed>  $attributes */
function updateCatalog(Product|Category $model, array $attributes): void
{
    if ($model instanceof Product) {
        app(UpdateProduct::class)->handle(ProductId::of($model), new UpdateProductPayload($attributes), origin());

        return;
    }

    if (array_key_exists('parent_id', $attributes)) {
        app(MoveCategory::class)->handle(CategoryId::of($model), new MoveCategoryPayload($attributes['parent_id'] === null ? null : new CategoryId($attributes['parent_id'])), origin());
        unset($attributes['parent_id']);
    }

    if ($attributes !== []) {
        app(UpdateCategory::class)->handle(CategoryId::of($model), new UpdateCategoryPayload(
            name: isset($attributes['name']) ? (is_array($attributes['name']) ? new Translatable($attributes['name']) : Translatable::fromString($attributes['name'])) : null,
            status: $attributes['status'] ?? null,
        ), origin());
    }
}

function deleteFromCatalog(Product|Category $model): void
{
    $model instanceof Product
        ? app(DeleteProduct::class)->handle(ProductId::of($model), origin())
        : app(DeleteCategory::class)->handle(CategoryId::of($model), origin());
}

/*
| Sales channels: tests run as if in a storefront request of the default channel, unless they say otherwise.
*/

use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Audit\Actors\SystemActor;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\Actions\UpdateSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesContext;
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
    app(UpdateSalesChannel::class)->handle(SalesChannelId::of($channel), ['locales' => $all], origin());

    foreach (EntryPoint::query()->where('sales_channel_id', $channel->id)->pluck('id') as $id) {
        app(DeleteEntryPoint::class)->handle(new EntryPointId((int) $id), origin());
    }

    foreach ($entryPoints as $entryPoint) {
        app(AddEntryPoint::class)->handle(SalesChannelId::of($channel), $entryPoint, origin());
    }

    config()->set('larasell.locale.supported', $locales);
    app(UpdateSalesChannel::class)->handle(SalesChannelId::of($channel), ['locales' => $locales, 'default_locale' => $entryPoints[0]['locale']], origin());

    StorefrontContext::for('default', $entryPoints[0]['locale'])->activate();
}

/** The sales context the test runs in: the default channel's, or the one shopLocales() set. */
function shopContext(): SalesContext
{
    return SalesContext::current() ?? SalesContext::for('default', Locale::ENGLISH);
}

/*
| Runs code outside the transaction RefreshDatabase wraps each test in, for checks that need to
| see no transaction at all. Write nothing in it: it would stay for later tests.
*/
function outsideTestTransaction(Closure $callback): mixed
{
    DB::rollBack();

    try {
        return $callback();
    } finally {
        DB::beginTransaction();
    }
}

/*
| Who the tests make their audited changes as.
*/
function origin(): Origin
{
    return Origin::cli(new SystemActor('tests'));
}

/**
 * An option with the given values, created unless it exists. Names are plain strings or translations.
 *
 * @param  array<int|string, mixed>  $values  Value slugs, or names keyed by value slug
 */
function optionDefinition(string $slug, array $values, mixed $name = null): OptionDefinition
{
    $definition = OptionDefinition::query()->firstOrCreate(['slug' => $slug], ['name' => $name ?? ['en' => ucfirst($slug)]]);

    $position = 0;

    foreach (array_is_list($values) ? array_fill_keys($values, null) : $values as $valueSlug => $valueName) {
        $definition->values()->firstOrCreate(
            ['slug' => (string) $valueSlug],
            ['name' => $valueName ?? ['en' => ucfirst((string) $valueSlug)], 'position' => $position++],
        );
    }

    return $definition;
}

function optionValue(OptionDefinition $definition, string $slug): OptionDefinitionValue
{
    return $definition->values()->where('slug', $slug)->sole();
}

/**
 * Gives the product the options, in order, and a visible variant for every combination of their values.
 * Each variant inherits the product's commerce values, the former default variant included.
 *
 * @param  list<OptionDefinition>  $definitions  Every value of each is offered
 * @return list<ProductVariant> In combination order, the first option varying slowest
 */
function optionVariants(Product $product, array $definitions): array
{
    $combinations = [[]];

    foreach ($definitions as $definition) {
        $values = $definition->values()->orderBy('position')->get()->all();
        $product->addOption($definition, $values);
        $combinations = array_merge(...array_map(
            fn (array $combination): array => array_map(fn (OptionDefinitionValue $value): array => [...$combination, $value], $values),
            $combinations,
        ));
    }

    return array_map(function (ProductVariant $variant): ProductVariant {
        $variant->update([
            'status' => Visibility::Visible,
            'price' => null,
            'compare_at' => null,
            'stock' => null,
            'allow_backorders' => null,
            'min_quantity' => null,
            'max_quantity' => null,
        ]);

        return $variant->refresh();
    }, $product->createCombinations($combinations));
}

/** The product's variant with the given value slugs, keyed by option slug. */
function optionVariant(Product $product, array $selection): ProductVariant
{
    return $product->variants()->get()->sole(fn (ProductVariant $variant): bool => collect($variant->options())->map(fn (array $option): string => $option['value_slug'])->sort()->all() == collect($selection)->sort()->all());
}
