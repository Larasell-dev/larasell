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
use Larasell\Larasell\Seo\Catalog\CustomUrls;
use Larasell\Larasell\Seo\Exceptions\InvalidSeoPath;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\ResolutionKind;
use Larasell\Larasell\Seo\SeoPages;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Translatable;

function customUrl(string $path, string $locale = 'en'): mixed
{
    return app(SeoPages::class)->resolve($path, StorefrontContext::for('default', $locale));
}

/** @param  array<string, string|null>  $paths */
function withUrls(array $paths): Attachments
{
    return new Attachments(new CustomUrls($paths));
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
        ->toThrow(InvalidSeoPath::class)
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
        ->toThrow(InvalidSeoPath::class)
        ->and(fn () => app(UpdateCategory::class)->handle(CategoryId::of($shoes), new UpdateCategoryPayload(new Translatable(['en' => 'Footwear']), attachments: withUrls(['en' => 'sale']))))
        ->toThrow(InvalidSeoPath::class)
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
