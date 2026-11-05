<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;
use Larasell\Larasell\SalesChannels\Actions\CreateSalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\Seo\Facades\Seo;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Settings\CurrencySettings;
use Larasell\Larasell\Storefront\Actions\AddEntryPoint;
use Larasell\Larasell\Storefront\StorefrontContext;

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

function linkContext(string $locale): StorefrontContext
{
    return StorefrontContext::for('default', $locale);
}

it('links to a page with the host and prefix of each context', function () {
    shopLocales(['en', 'de']);
    foreach (['uk' => ['https://shop.co.uk', 'en'], 'ch' => ['https://shop.ch:8443/de', 'de']] as $key => [$url, $locale]) {
        $channel = app(CreateSalesChannel::class)->handle([
            'key' => $key, 'name' => $key, 'type' => 'storefront', 'locales' => [$locale], 'currencies' => app(CurrencySettings::class)->enabled(),
        ]);
        app(AddEntryPoint::class)->handle(SalesChannelId::of($channel), ['url' => $url, 'locale' => $locale]);
    }
    $product = linkedProduct(['en' => 'Boots', 'de' => 'Stiefel']);

    expect(Seo::url($product, StorefrontContext::for('uk', 'en')))->toBe('https://shop.co.uk/p/boots')
        ->and(Seo::url($product, StorefrontContext::for('ch', 'de')))->toBe('https://shop.ch:8443/de/p/stiefel');
});

it('links in the context of the current storefront request', function () {
    shopLocales(['en', 'de']);
    $product = linkedProduct(['en' => 'Boots', 'de' => 'Stiefel']);
    Route::storefront(fn () => Route::get('/', fn () => Seo::url($product)));

    $this->get('/de')->assertSee('http://localhost/de/p/stiefel');
    $this->get('/en')->assertSee('http://localhost/en/p/boots');
});

it('has no URL where the page has none, and no available URL for hidden pages', function () {
    shopLocales(['en', 'de']);
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

it('links in no context it has to guess', function () {
    $product = linkedProduct(['en' => 'Boots']);
    Context::forgetHidden(StorefrontContext::KEY);

    expect(fn () => Seo::url($product))->toThrow(LogicException::class, 'StorefrontContext')
        ->and(fn () => Seo::preload([$product]))->toThrow(LogicException::class, 'StorefrontContext')
        ->and(Seo::url($product, StorefrontContext::for('default', 'en')))->toBe('http://localhost/p/boots');
});
