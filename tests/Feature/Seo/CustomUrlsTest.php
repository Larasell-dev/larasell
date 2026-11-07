<?php

use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateCategoryPayload;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Actions\CreateProductPayload;
use Larasell\Larasell\Catalog\Actions\UpdateCategory;
use Larasell\Larasell\Catalog\Actions\UpdateCategoryPayload;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;
use Larasell\Larasell\Catalog\Actions\UpdateProductPayload;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;
use Larasell\Larasell\SalesChannels\Actions\CreateSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\Seo\Catalog\CustomUrls;
use Larasell\Larasell\Seo\Exceptions\CustomUrlRejected;
use Larasell\Larasell\Seo\Exceptions\InvalidSeoPath;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\ResolutionKind;
use Larasell\Larasell\Seo\SeoPages;
use Larasell\Larasell\Settings\CurrencySettings;
use Larasell\Larasell\Storefront\Actions\AddEntryPoint;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Translatable;

function customUrl(string $path, string $locale = 'en'): mixed
{
    return app(SeoPages::class)->resolve($path, StorefrontContext::for('default', $locale));
}

/** @param  array<string, string|null>  $paths */
function ukChannel(): SalesChannelId
{
    $channel = app(CreateSalesChannel::class)->handle([
        'key' => 'uk', 'name' => 'UK', 'type' => 'storefront', 'locales' => ['en'], 'currencies' => app(CurrencySettings::class)->enabled(),
    ]);
    app(AddEntryPoint::class)->handle(SalesChannelId::of($channel), ['url' => 'https://shop.co.uk', 'locale' => 'en']);

    return SalesChannelId::of($channel);
}

function withUrls(array $paths): Attachments
{
    return new Attachments(CustomUrls::byLocale($paths));
}

it('creates a product with custom URLs per locale, next to its automatic ones', function () {
    shopLocales(['en', 'de']);

    $product = app(CreateProduct::class)->handle(new CreateProductPayload(
        ['name' => ['en' => 'Boots', 'de' => 'Stiefel'], 'price' => Price::of(1000), 'status' => Visibility::Visible],
        withUrls(['de' => 'winter-angebot']),
    ));

    expect(customUrl('winter-angebot', 'de')->resource?->is($product))->toBeTrue()
        ->and(customUrl('p/stiefel', 'de')->redirect?->url)->toBe('http://localhost/de/winter-angebot')
        ->and(customUrl('p/boots')->resource?->is($product))->toBeTrue();
});

it('creates no product when its custom URL is taken', function () {
    app(CreateProduct::class)->handle(new CreateProductPayload(['name' => ['en' => 'Sale'], 'price' => Price::of(1000)], withUrls(['en' => 'sale'])));

    expect(fn () => app(CreateProduct::class)->handle(new CreateProductPayload(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)], withUrls(['en' => 'sale']))))
        ->toThrow(CustomUrlRejected::class)
        ->and(Product::query()->count())->toBe(1)
        ->and(SeoUrl::query()->where('path', 'p/boots')->exists())->toBeFalse();
});

it('updates only a product\'s custom URL, and null goes back to the automatic URL', function () {
    $product = app(CreateProduct::class)->handle(new CreateProductPayload(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)]));

    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(['name' => ['en' => 'Leather Boots']], withUrls(['en' => 'boots-sale'])));

    expect(customUrl('boots-sale')->resource?->name->get('en'))->toBe('Leather Boots')
        ->and(customUrl('p/boots')->redirect?->url)->toBe('http://localhost/boots-sale');

    app(UpdateProduct::class)->handle(ProductId::of($product), new UpdateProductPayload(attachments: withUrls(['en' => null])));

    expect(customUrl('p/leather-boots')->kind)->toBe(ResolutionKind::Found)
        ->and(customUrl('boots-sale')->redirect?->url)->toBe('http://localhost/p/leather-boots');
});

it('creates and updates a category with a custom URL, and its subcategories follow', function () {
    $shoes = app(CreateCategory::class)->handle(new CreateCategoryPayload(new Translatable(['en' => 'Shoes']), attachments: withUrls(['en' => 'footwear'])));
    $boots = app(CreateCategory::class)->handle(new CreateCategoryPayload(new Translatable(['en' => 'Boots']), parentId: CategoryId::of($shoes)));

    expect(customUrl('footwear/boots')->resource?->is($boots))->toBeTrue();

    app(UpdateCategory::class)->handle(CategoryId::of($shoes), new UpdateCategoryPayload(new Translatable(['en' => 'All Shoes']), attachments: withUrls(['en' => 'all-shoes'])));

    expect(customUrl('all-shoes/boots')->resource?->is($boots))->toBeTrue()
        ->and(customUrl('footwear/boots')->redirect?->url)->toBe('http://localhost/all-shoes/boots')
        ->and(Category::query()->findOrFail($shoes->id)->name->get('en'))->toBe('All Shoes');
});

it('rolls back the category when its custom URL is taken', function () {
    $sale = app(CreateCategory::class)->handle(new CreateCategoryPayload(new Translatable(['en' => 'Sale']), attachments: withUrls(['en' => 'sale'])));
    $shoes = app(CreateCategory::class)->handle(new CreateCategoryPayload(new Translatable(['en' => 'Shoes'])));

    expect(fn () => app(CreateCategory::class)->handle(new CreateCategoryPayload(new Translatable(['en' => 'Boots']), attachments: withUrls(['en' => 'sale']))))
        ->toThrow(CustomUrlRejected::class)
        ->and(fn () => app(UpdateCategory::class)->handle(CategoryId::of($shoes), new UpdateCategoryPayload(new Translatable(['en' => 'Footwear']), attachments: withUrls(['en' => 'sale']))))
        ->toThrow(CustomUrlRejected::class)
        ->and(Category::query()->pluck('id')->all())->toBe([$sale->id, $shoes->id])
        ->and(Category::query()->findOrFail($shoes->id)->name->get('en'))->toBe('Shoes');
});

it('refuses a locale no channel serves before anything is written', function () {
    $queries = 0;
    DB::listen(function ($query) use (&$queries) {
        if (preg_match('/^\s*(insert|update|delete)/i', $query->sql)) {
            $queries++;
        }
    });

    expect(fn () => withUrls(['fr' => 'chaussures']))->toThrow(InvalidArgumentException::class)
        ->and($queries)->toBe(0)
        ->and(Category::query()->count())->toBe(0);
});

it('gives each channel of a locale its own custom URL', function () {
    $uk = ukChannel();
    $default = SalesChannelId::of(SalesChannel::query()->where('key', 'default')->sole());

    $shoes = app(CreateCategory::class)->handle(new CreateCategoryPayload(
        new Translatable(['en' => 'Shoes']),
        attachments: new Attachments(CustomUrls::byChannel('en', [$default->value => 'footwear', $uk->value => 'trainers'])),
    ));

    expect(customUrl('footwear')->resource?->is($shoes))->toBeTrue()
        ->and(app(SeoPages::class)->resolve('trainers', StorefrontContext::for('uk', 'en'))->resource?->is($shoes))->toBeTrue()
        ->and(app(SeoPages::class)->resolve('footwear', StorefrontContext::for('uk', 'en'))->resource)->toBeNull();
});

it('names the channel and reason of a rejected custom URL', function () {
    $uk = ukChannel();
    app(CreateCategory::class)->handle(new CreateCategoryPayload(new Translatable(['en' => 'Sale']), attachments: new Attachments(CustomUrls::byChannel('en', [$uk->value => 'sale']))));

    try {
        app(CreateCategory::class)->handle(new CreateCategoryPayload(new Translatable(['en' => 'Boots']), attachments: new Attachments(CustomUrls::byChannel('en', [$uk->value => 'sale']))));
        $this->fail('The taken path was accepted.');
    } catch (CustomUrlRejected $exception) {
        expect($exception->context->channelId->equals($uk))->toBeTrue()
            ->and($exception->reason)->toBe(InvalidSeoPath::TAKEN)
            ->and($exception->path)->toBe('sale')
            ->and($exception->getPrevious())->toBeInstanceOf(InvalidSeoPath::class);
    }

    expect(Category::query()->count())->toBe(1);
});

it('refuses a channel that does not serve the locale, or does not exist', function (Closure $channelId) {
    shopLocales(['en', 'de']);

    expect(fn () => CustomUrls::byChannel('fr', [$channelId() => 'chaussures']))->toThrow(InvalidArgumentException::class);
})->with([
    'wrong locale' => [fn () => SalesChannel::query()->where('key', 'default')->value('id')],
    'unknown channel' => [fn () => 999],
]);
