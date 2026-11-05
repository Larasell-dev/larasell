<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Events\ProductDeleted;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Contracts\Slugifier;
use Larasell\Larasell\Seo\DefaultSlugifier;
use Larasell\Larasell\Seo\Events\SeoUrlChanged;
use Larasell\Larasell\Seo\Events\SeoUrlReleased;
use Larasell\Larasell\Seo\InvalidSeoPath;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\ResolutionKind;
use Larasell\Larasell\Seo\SeoPages;
use Larasell\Larasell\Seo\SeoResources;
use Larasell\Larasell\Seo\SeoUrlManager;
use Larasell\Larasell\Seo\SeoUrlStatus;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Translatable;

beforeEach(function () {
    config()->set('queue.default', 'sync');
});

/** @param  string|array<string, string>  $name */
function seoProduct(string|array $name, Visibility $status = Visibility::Visible): Product
{
    return app(CreateProduct::class)->handle([
        'name' => is_string($name) ? ['en' => $name] : $name,
        'price' => Price::of(1000),
        'status' => $status,
    ]);
}

function seoCategory(string $name, ?Category $parent = null, Visibility $status = Visibility::Visible): Category
{
    return app(CreateCategory::class)->handle([
        'parent_id' => $parent?->getKey(),
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
        ->where('resource_type', app(SeoResources::class)->for($resource)->key())
        ->where('resource_id', $resource->getKey())
        ->where('locale', $locale)
        ->orderBy('path')
        ->pluck('status', 'path')
        ->map(fn ($status): string => $status->value)
        ->all();
}

function seoContext(string $locale = 'en'): SalesContext
{
    return collect(app(SeoUrlManager::class)->contexts())->first(fn (SalesContext $context): bool => $context->locale->value === $locale);
}

it('gives a new product a canonical URL', function () {
    $product = seoProduct('Leather Boots');

    expect(seoPaths($product))->toBe(['p/leather-boots' => 'canonical']);
});

it('keeps the old URL as history when a public product is renamed', function () {
    $product = seoProduct('Boots');
    updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);

    expect(seoPaths($product))->toBe([
        'p/boots' => 'historic',
        'p/leather-boots' => 'canonical',
    ]);
});

it('treats products and categories created without a status as public', function () {
    $product = app(CreateProduct::class)->handle(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]);
    $category = app(CreateCategory::class)->handle(['name' => ['en' => 'Shoes']]);

    updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);
    updateCatalog($category, ['name' => ['en' => 'Footwear']]);

    expect(seoPaths($product))->toBe(['p/boots' => 'historic', 'p/leather-boots' => 'canonical'])
        ->and(seoPaths($category))->toBe(['footwear' => 'canonical', 'shoes' => 'historic']);
});

it('reuses the old URL when a product is renamed back', function () {
    $product = seoProduct('Boots');
    updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);
    updateCatalog($product, ['name' => ['en' => 'Boots']]);

    expect(seoPaths($product))->toBe([
        'p/boots' => 'canonical',
        'p/leather-boots' => 'historic',
    ]);
});

it('keeps no history while a product has never been public', function () {
    $product = seoProduct('Draft', Visibility::Hidden);
    updateCatalog($product, ['name' => ['en' => 'Second Draft']]);
    updateCatalog($product, ['name' => ['en' => 'Boots']]);

    expect(seoPaths($product))->toBe(['p/boots' => 'canonical']);

    updateCatalog($product, ['status' => Visibility::Visible]);
    updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);

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
    deleteFromCatalog($old);

    expect(seoPaths($old))->toBe(['p/boots' => 'gone']);

    $new = seoProduct('Boots');

    expect(seoPaths($new))->toBe(['p/boots' => 'canonical'])
        ->and(seoPaths($old))->toBe([]);
    Event::assertDispatched(SeoUrlReleased::class, fn (SeoUrlReleased $event): bool => $event->path === 'p/boots');
});

it('keeps a custom URL through renames until it is reset', function () {
    $manager = app(SeoUrlManager::class);
    $product = seoProduct('Boots');

    $manager->setCustom($product, seoContext(), '/Winter-Sale/');
    updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);

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

    updateCatalog($shoes, ['parent_id' => $sale->getKey()]);

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

    deleteFromCatalog($shoes);
    deleteFromCatalog($draft);

    $gone = SeoUrl::query()->where('path', 'shoes')->sole();

    expect($gone->status->value)->toBe('gone')
        ->and($gone->gone_snapshot)->toBe(['name' => 'Shoes', 'parent_id' => null])
        ->and(seoPaths($boots))->toBe(['boots' => 'canonical', 'shoes/boots' => 'historic'])
        ->and(seoPaths($draft))->toBe([]);
});

it('gives a product a URL only in the locales it has a name in', function () {
    shopLocales(['en', 'de-DE']);

    $product = seoProduct(['en' => 'Size Chart']);
    expect(seoPaths($product, 'de-DE'))->toBe([]);

    updateCatalog($product, ['name' => ['en' => 'Size Chart', 'de' => 'Größentabelle']]);

    expect(seoPaths($product, 'en'))->toBe(['p/size-chart' => 'canonical'])
        ->and(seoPaths($product, 'de-DE'))->toBe(['p/groessentabelle' => 'canonical'])
        ->and(StorefrontContext::for(seoContext('de-DE'))->url('p/groessentabelle'))->toBe('http://localhost/de-de/p/groessentabelle');
});

it('keeps exactly one canonical URL and no duplicate paths through any sequence of changes', function () {
    $manager = app(SeoUrlManager::class);
    $product = seoProduct('Boots');
    seoProduct('Shoes');

    $steps = [
        fn () => updateCatalog($product, ['name' => ['en' => 'Shoes']]),
        fn () => $manager->setCustom($product, seoContext(), 'sale'),
        fn () => updateCatalog($product, ['name' => ['en' => 'Boots']]),
        fn () => $manager->resetToAutomatic($product, seoContext()),
        fn () => $manager->setCustom($product, seoContext(), 'p/shoes-2'),
        fn () => $manager->resetToAutomatic($product, seoContext()),
        fn () => updateCatalog($product, ['name' => ['en' => 'Sale']]),
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
    Event::fake([SeoUrlChanged::class]);

    $this->artisan('larasell:regenerate-seo-urls', ['--dry-run' => true])
        ->expectsOutputToContain('p/leather-boots')
        ->expectsOutputToContain('1 URL would change.')
        ->assertSuccessful();

    expect(seoPaths($product))->toBe(['p/boots' => 'canonical']);
    Event::assertNotDispatched(SeoUrlChanged::class);

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
    config()->set('larasell.locale.default', Locale::GERMAN);
    shopLocales(['de']);

    expect(seoPaths(seoProduct(['de' => 'Schöne Grüße']), 'de'))->toBe(['p/schoene-gruesse' => 'canonical']);
});

/** @return array<string, string> Every stored path with its status, for checks that must not filter by type. */
function allSeoPaths(): array
{
    return SeoUrl::query()->pluck('status', 'path')->map(fn ($status): string => $status->value)->all();
}

it('keeps the URLs of a page when the app adds a morph map later', function () {
    $product = seoProduct('Boots');
    Relation::morphMap(['shop-product' => Product::class]);

    try {
        updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);

        expect(allSeoPaths())->toEqual(['p/boots' => 'historic', 'p/leather-boots' => 'canonical'])
            ->and(app(SeoPages::class)->url($product, StorefrontContext::for(seoContext())))->toBe('http://localhost/p/leather-boots');
    } finally {
        Relation::morphMap([], false);
    }
});

it('lets the database refuse a second canonical URL for the same page and context', function () {
    seoProduct('Boots');
    $current = SeoUrl::query()->where('locale', 'en')->sole();
    $row = fn (string $path, SeoUrlStatus $status): array => [
        ...$current->only(['channel', 'locale', 'resource_type', 'resource_id']),
        'path' => $path,
        'status' => $status,
    ];

    // Any number of old URLs is fine, and so is a canonical URL in another locale.
    SeoUrl::query()->create($row('p/old-boots', SeoUrlStatus::Historic));
    SeoUrl::query()->create($row('p/older-boots', SeoUrlStatus::Historic));
    SeoUrl::query()->create([...$row('p/bottes', SeoUrlStatus::Canonical), 'locale' => 'fr']);

    expect(fn () => SeoUrl::query()->create($row('p/other-boots', SeoUrlStatus::Canonical)))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('treats paths that differ only in accents as different paths', function () {
    $manager = app(SeoUrlManager::class);

    $coffee = seoProduct('Coffee');
    $manager->setCustom($coffee, seoContext(), 'café');
    $manager->setCustom($coffee, seoContext(), 'cafe');

    $street = seoProduct('Street');
    $manager->setCustom($street, seoContext(), 'straße');
    $manager->setCustom(seoProduct('Strasse'), seoContext(), 'strasse');

    $tea = seoProduct('Tea');
    $manager->setCustom($tea, seoContext(), 'thé');

    expect(seoPaths($coffee))->toEqual(['p/coffee' => 'historic', 'café' => 'historic', 'cafe' => 'canonical'])
        ->and(seoPaths($street))->toEqual(['p/street' => 'historic', 'straße' => 'canonical'])
        ->and(app(SeoPages::class)->resolve('strasse', StorefrontContext::for(seoContext()))->resource?->is($street))->toBeFalse()
        ->and(app(SeoPages::class)->resolve('the', StorefrontContext::for(seoContext()))->kind)->toBe(ResolutionKind::NotFound);
});

it('announces URL changes only once they are committed', function () {
    $announced = [];
    Event::listen(SeoUrlChanged::class, function (SeoUrlChanged $event) use (&$announced): void {
        $announced[] = $event->newPath;
    });
    $product = seoProduct('Boots');

    try {
        DB::transaction(function () use ($product): void {
            updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);

            throw new RuntimeException('Rolled back.');
        });
    } catch (RuntimeException) {
    }

    expect($announced)->toBe(['p/boots'])
        ->and(seoPaths($product))->toBe(['p/boots' => 'canonical']);

    updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);

    expect($announced)->toBe(['p/boots', 'p/leather-boots']);
});

it('redirects a deleted page to a replacement chosen after the delete', function () {
    $old = seoProduct('Boots');
    $new = seoProduct('Boots 2027');

    deleteFromCatalog($old);
    app(SeoUrlManager::class)->markGone($old, $new);

    expect(app(SeoPages::class)->resolve('p/boots', StorefrontContext::for(seoContext()))->redirect?->url)->toBe('http://localhost/p/boots-2027');
});

/** Slugs like the default one, but fails for the name "Kaputt", e.g. a transliteration service that is down. */
class FailingSeoSlugifier implements Slugifier
{
    public function slug(Translatable $text, Locale $locale): ?string
    {
        $slug = (new DefaultSlugifier)->slug($text, $locale);

        return $slug === 'kaputt' ? throw new RuntimeException('Slugifier failed.') : $slug;
    }
}

it('gives a new product its URL and a deleted one its tombstone in the same write', function () {
    $product = seoProduct('Boots');

    expect(seoPaths($product))->toBe(['p/boots' => 'canonical']);

    deleteFromCatalog($product);

    expect(seoPaths($product))->toBe(['p/boots' => 'gone'])
        ->and(SeoUrl::query()->sole()->gone_snapshot)->toBe(['name' => 'Boots', 'category_ids' => []]);
});

it('keeps a product and its URLs as they were when its delete fails', function () {
    $product = seoProduct('Boots');
    // Another listener stops the delete after the SEO listener ran, as a foreign key or lost connection would.
    Event::listen(ProductDeleted::class, fn () => throw new RuntimeException('Delete failed.'));

    expect(fn () => deleteFromCatalog($product))->toThrow(RuntimeException::class, 'Delete failed.');

    expect(Product::query()->whereKey($product->getKey())->exists())->toBeTrue()
        ->and(seoPaths($product))->toBe(['p/boots' => 'canonical'])
        ->and(app(SeoPages::class)->resolve('p/boots', StorefrontContext::for(seoContext()))->kind)->toBe(ResolutionKind::Found);
});

it('saves a product and its URLs in every locale together, or none of them', function () {
    shopLocales(['en', 'de-DE']);
    config()->set('larasell.seo.slugifier', FailingSeoSlugifier::class);
    $product = seoProduct(['en' => 'Boots', 'de' => 'Stiefel']);

    expect(fn () => updateCatalog($product, ['name' => ['en' => 'Leather Boots', 'de' => 'Kaputt']]))
        ->toThrow(RuntimeException::class, 'Slugifier failed.');

    expect(Product::query()->findOrFail($product->getKey())->name->get('en'))->toBe('Boots')
        ->and(seoPaths($product, 'en'))->toBe(['p/boots' => 'canonical'])
        ->and(seoPaths($product, 'de-DE'))->toBe(['p/stiefel' => 'canonical']);
});

it('leaves URLs alone when a model is written directly instead of through a catalog action', function () {
    $product = seoProduct('Boots');

    $product->update(['name' => ['en' => 'Leather Boots']]);
    Product::query()->findOrFail($product->getKey())->delete();

    expect(seoPaths($product))->toBe(['p/boots' => 'canonical']);
});

it('moves the URLs of every descendant in the same write, without queueing anything', function () {
    Queue::fake();
    $shoes = seoCategory('Shoes');
    $boots = seoCategory('Boots', $shoes);
    $winter = seoCategory('Winter', $boots);

    updateCatalog($shoes, ['name' => ['en' => 'Footwear']]);

    expect(seoPaths($winter))->toBe(['footwear/boots/winter' => 'canonical', 'shoes/boots/winter' => 'historic']);
    Queue::assertNothingPushed();
});

it('moves a category back if the URL of one of its descendants cannot be written', function () {
    $sale = seoCategory('Sale');
    $shoes = seoCategory('Shoes');
    $kaputt = seoCategory('Kaputt', $shoes);
    config()->set('larasell.seo.slugifier', FailingSeoSlugifier::class);

    expect(fn () => updateCatalog($shoes, ['parent_id' => $sale->getKey()]))->toThrow(RuntimeException::class, 'Slugifier failed.')
        ->and(Category::query()->findOrFail($shoes->getKey())->parent_id)->toBeNull()
        ->and(seoPaths($shoes))->toBe(['shoes' => 'canonical'])
        ->and(seoPaths($kaputt))->toBe(['shoes/kaputt' => 'canonical']);
});

it('brings no URL back for a page refreshed from a copy loaded before its delete', function () {
    $product = seoProduct('Boots');
    $stale = Product::query()->findOrFail($product->getKey());
    deleteFromCatalog($product);

    expect(app(SeoUrlManager::class)->refresh($stale))->toBe([])
        ->and(seoPaths($product))->toBe(['p/boots' => 'gone']);
});

it('stops instead of looping when categories form a cycle that bypassed MoveCategory', function () {
    $a = seoCategory('A');
    $b = seoCategory('B', $a);
    Category::query()->whereKey($a->getKey())->update(['parent_id' => $b->getKey()]);

    updateCatalog($a, ['name' => ['en' => 'A2']]);
})->throws(LogicException::class, 'cycle');

it('stores and finds paths of any length', function () {
    $name = str_repeat('Very long boots ', 40);
    $product = seoProduct($name);
    $automatic = 'p/'.Str::slug($name);
    $custom = str_repeat('winter-sale/', 60).'boots';

    expect(strlen($automatic))->toBeGreaterThan(600)
        ->and(seoPaths($product))->toBe([$automatic => 'canonical'])
        ->and(app(SeoPages::class)->resolve($automatic, StorefrontContext::for(seoContext()))->resource?->is($product))->toBeTrue();

    app(SeoUrlManager::class)->setCustom($product, seoContext(), $custom);

    expect(app(SeoPages::class)->resolve($custom, StorefrontContext::for(seoContext()))->resource?->is($product))->toBeTrue()
        ->and(app(SeoPages::class)->resolve($automatic, StorefrontContext::for(seoContext()))->redirect?->url)->toBe('http://localhost/'.$custom);
});

it('numbers any number of pages with the same name, with one lookup for the taken numbers', function () {
    foreach (range(1, 100) as $i) {
        seoProduct('Boots');
    }
    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        $queries += str_contains($query->sql, 'larasell_seo_urls') ? 1 : 0;
    });

    $product = seoProduct('Boots');

    expect(seoPaths($product))->toBe(['p/boots-101' => 'canonical'])
        ->and($queries)->toBeLessThan(15);
});

it('gives a page the lowest free number', function () {
    seoProduct('Boots');
    $second = seoProduct('Boots');
    seoProduct('Boots');
    app(SeoUrlManager::class)->setCustom($second, seoContext(), 'second-boots');
    app(SeoUrlManager::class)->remove(SeoUrl::query()->where('path', 'p/boots-2')->sole());

    expect(seoPaths(seoProduct('Boots')))->toBe(['p/boots-2' => 'canonical'])
        ->and(seoPaths(seoProduct('Boots')))->toBe(['p/boots-4' => 'canonical']);
});

it('gives existing pages their URLs in a locale a channel gains, in a queued job', function () {
    config()->set('queue.default', 'sync');
    config()->set('larasell.locale.supported', ['en', 'de']);
    $product = seoProduct(['en' => 'Boots', 'de' => 'Stiefel']);
    $category = app(CreateCategory::class)->handle(['name' => ['en' => 'Shoes', 'de' => 'Schuhe']]);

    expect(seoPaths($product, 'de'))->toBe([]);

    shopLocales(['en', 'de']);

    expect(seoPaths($product, 'de'))->toBe(['p/stiefel' => 'canonical'])
        ->and(seoPaths($category, 'de'))->toBe(['schuhe' => 'canonical'])
        ->and(seoPaths($product, 'en'))->toBe(['p/boots' => 'canonical']);
});
