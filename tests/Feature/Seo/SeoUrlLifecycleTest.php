<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Larasell\Larasell\Seo\Events\SeoUrlReleased;
use Larasell\Larasell\Seo\InvalidSeoPath;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SeoContext;
use Larasell\Larasell\Seo\SeoUrlManager;

beforeEach(function () {
    config()->set('queue.default', 'sync');
});

/** @param  string|array<string, string>  $name */
function seoProduct(string|array $name, Visibility $status = Visibility::Visible): Product
{
    return Product::create([
        'slug' => fake()->unique()->slug(),
        'name' => is_string($name) ? ['en' => $name] : $name,
        'price' => Price::of(1000),
        'status' => $status,
    ]);
}

function seoCategory(string $name, ?Category $parent = null, Visibility $status = Visibility::Visible): Category
{
    return Category::create([
        'parent_id' => $parent?->getKey(),
        'slug' => fake()->unique()->slug(),
        'name' => ['en' => $name],
        'status' => $status,
    ]);
}

/**
 * The resource's paths in one locale, keyed by path, with their status.
 *
 * @return array<string, string>
 */
function seoPaths(Model $resource, string $locale = 'en'): array
{
    return SeoUrl::query()
        ->where('resource_type', $resource->getMorphClass())
        ->where('resource_id', $resource->getKey())
        ->where('locale', $locale)
        ->orderBy('path')
        ->pluck('status', 'path')
        ->map(fn ($status): string => $status->value)
        ->all();
}

function seoContext(string $locale = 'en'): SeoContext
{
    return collect(app(SeoUrlManager::class)->contexts())->first(fn (SeoContext $context): bool => $context->locale->value === $locale);
}

it('gives a new product a canonical URL', function () {
    $product = seoProduct('Leather Boots');

    expect(seoPaths($product))->toBe(['p/leather-boots' => 'canonical']);
});

it('keeps the old URL as history when a public product is renamed', function () {
    $product = seoProduct('Boots');
    $product->update(['name' => ['en' => 'Leather Boots']]);

    expect(seoPaths($product))->toBe([
        'p/boots' => 'historic',
        'p/leather-boots' => 'canonical',
    ]);
});

it('treats products and categories created without a status as public', function () {
    $product = Product::create(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);
    $category = Category::create(['name' => ['en' => 'Shoes']]);

    $product->update(['name' => ['en' => 'Leather Boots']]);
    $category->update(['name' => ['en' => 'Footwear']]);

    expect(seoPaths($product))->toBe(['p/boots' => 'historic', 'p/leather-boots' => 'canonical'])
        ->and(seoPaths($category))->toBe(['footwear' => 'canonical', 'shoes' => 'historic']);
});

it('reuses the old URL when a product is renamed back', function () {
    $product = seoProduct('Boots');
    $product->update(['name' => ['en' => 'Leather Boots']]);
    $product->update(['name' => ['en' => 'Boots']]);

    expect(seoPaths($product))->toBe([
        'p/boots' => 'canonical',
        'p/leather-boots' => 'historic',
    ]);
});

it('keeps no history while a product has never been public', function () {
    $product = seoProduct('Draft', Visibility::Hidden);
    $product->update(['name' => ['en' => 'Second Draft']]);
    $product->update(['name' => ['en' => 'Boots']]);

    expect(seoPaths($product))->toBe(['p/boots' => 'canonical']);

    $product->update(['status' => Visibility::Visible]);
    $product->update(['name' => ['en' => 'Leather Boots']]);

    expect(seoPaths($product))->toBe([
        'p/boots' => 'historic',
        'p/leather-boots' => 'canonical',
    ]);
});

it('suffixes an automatic path that another page uses', function () {
    $first = seoProduct('Boots');
    $second = seoProduct('Boots');
    $third = seoProduct('Boots');

    expect(seoPaths($first))->toBe(['p/boots' => 'canonical'])
        ->and(seoPaths($second))->toBe(['p/boots-2' => 'canonical'])
        ->and(seoPaths($third))->toBe(['p/boots-3' => 'canonical']);
});

it('keeps categories out of the product prefix', function () {
    $p = seoCategory('P');
    $child = seoCategory('Boots', $p);

    expect(seoPaths($p))->toBe(['p-2' => 'canonical'])
        ->and(seoPaths($child))->toBe(['p-2/boots' => 'canonical']);

    app(SeoUrlManager::class)->setCustom($p, seoContext(), 'p/sale');
})->throws(InvalidSeoPath::class, 'reserved');

it('lets a new page claim the URL of a deleted one', function () {
    Event::fake([SeoUrlReleased::class]);
    $old = seoProduct('Boots');
    $old->delete();

    expect(seoPaths($old))->toBe(['p/boots' => 'gone']);

    $new = seoProduct('Boots');

    expect(seoPaths($new))->toBe(['p/boots' => 'canonical'])
        ->and(seoPaths($old))->toBe([]);
    Event::assertDispatched(SeoUrlReleased::class, fn (SeoUrlReleased $event): bool => $event->url->path === 'p/boots');
});

it('keeps a custom URL through renames until it is reset', function () {
    $manager = app(SeoUrlManager::class);
    $product = seoProduct('Boots');

    $manager->setCustom($product, seoContext(), '/Winter-Sale/');
    $product->update(['name' => ['en' => 'Leather Boots']]);

    expect(seoPaths($product))->toBe([
        'p/boots' => 'historic',
        'winter-sale' => 'canonical',
    ]);

    $manager->resetToAutomatic($product, seoContext());

    expect(seoPaths($product))->toBe([
        'p/boots' => 'historic',
        'p/leather-boots' => 'canonical',
        'winter-sale' => 'historic',
    ]);
});

it('rejects a custom URL that another page uses or that is malformed', function (string $path, string $message) {
    seoProduct('Boots');
    $product = seoProduct('Shoes');

    expect(fn () => app(SeoUrlManager::class)->setCustom($product, seoContext(), $path))
        ->toThrow(InvalidSeoPath::class, $message);
})->with([
    'taken' => ['p/boots', 'already used'],
    'query string' => ['sale?page=2', 'not a valid'],
    'spaces' => ['winter sale', 'not a valid'],
]);

it('moves the URLs of child categories along with their parent', function () {
    $sale = seoCategory('Sale');
    $shoes = seoCategory('Shoes');
    $boots = seoCategory('Boots', $shoes);
    $sandals = seoCategory('Sandals', $shoes);
    app(SeoUrlManager::class)->setCustom($sandals, seoContext(), 'summer');

    $shoes->update(['parent_id' => $sale->getKey()]);

    expect(seoPaths($shoes))->toBe(['sale/shoes' => 'canonical', 'shoes' => 'historic'])
        ->and(seoPaths($boots))->toBe(['sale/shoes/boots' => 'canonical', 'shoes/boots' => 'historic'])
        ->and(seoPaths($sandals))->toBe(['shoes/sandals' => 'historic', 'summer' => 'canonical']);
});

it('builds child URLs on the parent custom URL', function () {
    $shoes = seoCategory('Shoes');
    $boots = seoCategory('Boots', $shoes);

    app(SeoUrlManager::class)->setCustom($shoes, seoContext(), 'footwear');

    expect(seoPaths($boots))->toBe(['footwear/boots' => 'canonical', 'shoes/boots' => 'historic']);
});

it('keeps the public URLs of a deleted category and moves its children up', function () {
    $shoes = seoCategory('Shoes');
    $boots = seoCategory('Boots', $shoes);
    $draft = seoCategory('Draft', status: Visibility::Hidden);

    $shoes->delete();
    $draft->delete();

    $gone = SeoUrl::query()->where('path', 'shoes')->sole();

    expect($gone->status->value)->toBe('gone')
        ->and($gone->gone_snapshot)->toBe(['name' => 'Shoes', 'parent_id' => null])
        ->and(seoPaths($boots))->toBe(['boots' => 'canonical', 'shoes/boots' => 'historic'])
        ->and(seoPaths($draft))->toBe([]);
});

it('gives a product a URL only in the locales it has a name in', function () {
    config()->set('larasell.locale.supported', ['en', 'de-DE']);

    $product = seoProduct(['en' => 'Size Chart']);
    expect(seoPaths($product, 'de-DE'))->toBe([]);

    $product->update(['name' => ['en' => 'Size Chart', 'de' => 'Größentabelle']]);

    expect(seoPaths($product, 'en'))->toBe(['p/size-chart' => 'canonical'])
        ->and(seoPaths($product, 'de-DE'))->toBe(['p/groessentabelle' => 'canonical'])
        ->and(seoContext('de-DE')->url('p/groessentabelle'))->toBe('http://localhost/de-de/p/groessentabelle');
});

it('keeps exactly one canonical URL and no duplicate paths through any sequence of changes', function () {
    $manager = app(SeoUrlManager::class);
    $product = seoProduct('Boots');
    seoProduct('Shoes');

    $steps = [
        fn () => $product->update(['name' => ['en' => 'Shoes']]),
        fn () => $manager->setCustom($product, seoContext(), 'sale'),
        fn () => $product->update(['name' => ['en' => 'Boots']]),
        fn () => $manager->resetToAutomatic($product, seoContext()),
        fn () => $manager->setCustom($product, seoContext(), 'p/shoes-2'),
        fn () => $manager->resetToAutomatic($product, seoContext()),
        fn () => $product->update(['name' => ['en' => 'Sale']]),
    ];

    foreach ($steps as $step) {
        $step();

        expect(array_count_values(seoPaths($product))['canonical'])->toBe(1)
            ->and(SeoUrl::query()->count())->toBe(SeoUrl::query()->distinct()->count('path'));
    }

    expect(seoPaths($product))->toBe([
        'p/boots' => 'historic',
        'p/sale' => 'canonical',
        'p/shoes-2' => 'historic',
        'sale' => 'historic',
    ]);
});

it('regenerates URLs that bulk updates left behind', function () {
    $product = seoProduct('Boots');
    Product::query()->whereKey($product->getKey())->update(['name' => json_encode(['en' => 'Leather Boots'])]);

    $this->artisan('larasell:regenerate-seo-urls', ['--dry-run' => true])
        ->expectsOutputToContain('1 URL would change.')
        ->assertSuccessful();

    expect(seoPaths($product))->toBe(['p/boots' => 'canonical']);

    $this->artisan('larasell:regenerate-seo-urls', ['type' => 'product'])
        ->expectsOutputToContain('1 URL changed.')
        ->assertSuccessful();

    expect(seoPaths($product))->toBe(['p/boots' => 'historic', 'p/leather-boots' => 'canonical']);
});

it('refuses to remove a canonical URL', function () {
    seoProduct('Boots');

    app(SeoUrlManager::class)->remove(SeoUrl::query()->sole());
})->throws(LogicException::class);

it('uses German transliteration for German paths', function () {
    config()->set('larasell.locale.supported', [Locale::GERMAN]);
    config()->set('larasell.locale.default', Locale::GERMAN);

    expect(seoPaths(seoProduct(['de' => 'Schöne Grüße']), 'de'))->toBe(['p/schoene-gruesse' => 'canonical']);
});
