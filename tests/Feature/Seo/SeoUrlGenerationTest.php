<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;
use Larasell\Larasell\Seo\Facades\Seo;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SeoContext;
use Larasell\Larasell\Seo\SeoUrlManager;

beforeEach(function () {
    config()->set('queue.default', 'sync');
});

/** @param  array<string, string>  $name */
function linkedProduct(array $name, Visibility $status = Visibility::Visible): Product
{
    return app(CreateProduct::class)->handle([
        'name' => $name,
        'price' => Price::of(1000),
        'status' => $status,
    ]);
}

function linkContext(string $locale): SeoContext
{
    return collect(app(SeoUrlManager::class)->contexts())->first(fn (SeoContext $context): bool => $context->locale->value === $locale);
}

it('links to a page with the host and prefix of each context', function () {
    config()->set('larasell.locale.supported', ['en', 'de']);
    config()->set('larasell.seo.channels', [
        'uk' => ['entry_points' => [['url' => 'https://shop.co.uk', 'locale' => 'en']]],
        'ch' => ['entry_points' => [['url' => 'https://shop.ch:8443/de', 'locale' => 'de']]],
    ]);
    $product = linkedProduct(['en' => 'Boots', 'de' => 'Stiefel']);

    expect(Seo::url($product, linkContext('en')))->toBe('https://shop.co.uk/p/boots')
        ->and(Seo::url($product, linkContext('de')))->toBe('https://shop.ch:8443/de/p/stiefel');
});

it('links in the context of the current storefront request', function () {
    config()->set('larasell.locale.supported', ['en', 'de']);
    $product = linkedProduct(['en' => 'Boots', 'de' => 'Stiefel']);
    Route::storefront(fn () => Route::get('/', fn () => Seo::url($product)));

    $this->get('/de')->assertSee('http://localhost/de/p/stiefel');
    $this->get('/en')->assertSee('http://localhost/en/p/boots');
});

it('has no URL where the page has none, and no available URL for hidden pages', function () {
    config()->set('larasell.locale.supported', ['en', 'de']);
    $english = linkedProduct(['en' => 'Size Chart']);
    $hidden = linkedProduct(['en' => 'Secret'], Visibility::Hidden);

    expect(Seo::url($english, linkContext('de')))->toBeNull()
        ->and(Seo::url($hidden, linkContext('en')))->toBe('http://localhost/en/p/secret')
        ->and(Seo::availableUrl($hidden, linkContext('en')))->toBeNull();
});

it('loads the URLs of a listing in one query per kind of page', function () {
    $products = collect(range(1, 5))->map(fn (int $i) => linkedProduct(['en' => "Boots {$i}"]));
    $category = app(CreateCategory::class)->handle(['name' => ['en' => 'Shoes']]);
    $unsaved = new Product(['name' => ['en' => 'Unsaved']]);
    $unsaved->id = 999;

    DB::enableQueryLog();
    Seo::preload([...$products, $category, $unsaved]);
    $urls = [...$products->map(fn (Product $product) => Seo::url($product)), Seo::url($category), Seo::url($unsaved)];

    expect(DB::getQueryLog())->toHaveCount(2)
        ->and($urls)->toBe([
            'http://localhost/p/boots-1', 'http://localhost/p/boots-2', 'http://localhost/p/boots-3',
            'http://localhost/p/boots-4', 'http://localhost/p/boots-5', 'http://localhost/shoes', null,
        ]);
});

it('does not link to a preloaded path after it changed', function () {
    $product = linkedProduct(['en' => 'Boots']);
    Seo::preload([$product]);

    updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);

    expect(Seo::url($product))->toBe('http://localhost/p/leather-boots');
});

it('forgets preloaded paths between requests and jobs of a long-running process', function () {
    $product = linkedProduct(['en' => 'Boots']);
    Seo::preload([$product]);

    // Another process changes the URL, so no event reaches this one.
    SeoUrl::query()->where('path', 'p/boots')->update(['path' => 'p/leather-boots']);

    expect(Seo::url($product))->toBe('http://localhost/p/boots');

    app()->forgetScopedInstances(); // what Octane and the queue worker do between requests and jobs

    expect(Seo::url($product))->toBe('http://localhost/p/leather-boots');
});
