<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Exceptions\ProductInUse;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Price;
use Larasell\Larasell\Seo\InvalidSeoPath;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\ResolutionKind;
use Larasell\Larasell\Seo\SeoPages;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Workflows\CreateCategoryWithUrls;
use Larasell\Larasell\Workflows\CreateProductWithUrls;
use Larasell\Larasell\Workflows\DiscontinueProduct;
use Larasell\Larasell\Workflows\UpdateCategoryWithUrls;
use Larasell\Larasell\Workflows\UpdateProductWithUrls;

function workflowProduct(string $name, int $stock = 0): Product
{
    return app(CreateProduct::class)->handle([
        'name' => ['en' => $name],
        'price' => Price::of(1000),
        'stock' => $stock,
        'status' => Visibility::Visible,
    ]);
}

function resolveUrl(string $path, string $locale = 'en'): mixed
{
    return app(SeoPages::class)->resolve($path, StorefrontContext::for('default', $locale));
}

it('discontinues a product and sends its URLs to the replacement', function () {
    $old = workflowProduct('Boots 2026');
    $new = workflowProduct('Boots 2027');

    app(DiscontinueProduct::class)->handle(ProductId::of($old), ProductId::of($new));

    expect(Product::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(resolveUrl('p/boots-2026')->redirect?->url)->toBe('http://localhost/p/boots-2027')
        ->and(resolveUrl('p/boots-2026')->redirect?->status)->toBe(301);
});

it('discontinues a product without a replacement, so its URLs answer 410', function () {
    $old = workflowProduct('Boots');

    app(DiscontinueProduct::class)->handle(ProductId::of($old));

    expect(resolveUrl('p/boots')->kind)->toBe(ResolutionKind::Gone);
});

it('refuses a replacement that is the product itself or does not exist, and keeps the product', function (Closure $replacement, string $exception) {
    $product = workflowProduct('Boots');

    expect(fn () => app(DiscontinueProduct::class)->handle(ProductId::of($product), $replacement($product)))->toThrow($exception)
        ->and(Product::query()->whereKey($product->id)->exists())->toBeTrue()
        ->and(resolveUrl('p/boots')->kind)->toBe(ResolutionKind::Found);
})->with([
    'itself' => [fn (Product $product) => ProductId::of($product), LogicException::class],
    'missing' => [fn (Product $product) => new ProductId($product->id + 100), ModelNotFoundException::class],
]);

it('keeps a product of an open order with its URL, and sets no replacement', function () {
    $product = workflowProduct('Boots', stock: 5);
    $new = workflowProduct('Boots 2027');
    $cart = Cart::createIn(shopContext(), ['currency' => Currency::EUR]);
    $cart->add($product, 1);
    app(Checkout::class)->create($cart, ['customer_email' => 'a@example.com', 'customer_name' => 'A']);

    expect(fn () => app(DiscontinueProduct::class)->handle(ProductId::of($product), ProductId::of($new)))->toThrow(ProductInUse::class)
        ->and(resolveUrl('p/boots')->kind)->toBe(ResolutionKind::Found)
        ->and(SeoUrl::query()->whereNotNull('replacement_id')->exists())->toBeFalse();
});

it('creates a product with custom URLs per locale, next to its automatic ones', function () {
    shopLocales(['en', 'de']);

    $product = app(CreateProductWithUrls::class)->handle(
        ['name' => ['en' => 'Boots', 'de' => 'Stiefel'], 'price' => Price::of(1000), 'status' => Visibility::Visible],
        ['de' => 'winter-angebot'],
    );

    expect(resolveUrl('winter-angebot', 'de')->resource?->is($product))->toBeTrue()
        ->and(resolveUrl('p/stiefel', 'de')->redirect?->url)->toBe('http://localhost/de/winter-angebot')
        ->and(resolveUrl('p/boots')->resource?->is($product))->toBeTrue();
});

it('creates no product when its custom URL cannot be used', function () {
    app(CreateProductWithUrls::class)->handle(['name' => ['en' => 'Sale'], 'price' => Price::of(1000)], ['en' => 'sale']);

    expect(fn () => app(CreateProductWithUrls::class)->handle(['name' => ['en' => 'Boots'], 'price' => Price::of(1000)], ['en' => 'sale']))
        ->toThrow(InvalidSeoPath::class)
        ->and(Product::query()->count())->toBe(1)
        ->and(SeoUrl::query()->where('path', 'p/boots')->exists())->toBeFalse();
});

it('updates a product and its custom URL together, and null goes back to the automatic URL', function () {
    $product = workflowProduct('Boots');

    app(UpdateProductWithUrls::class)->handle(ProductId::of($product), ['name' => ['en' => 'Leather Boots']], ['en' => 'boots-sale']);

    expect(resolveUrl('boots-sale')->resource?->name->get('en'))->toBe('Leather Boots')
        ->and(resolveUrl('p/boots')->redirect?->url)->toBe('http://localhost/boots-sale');

    app(UpdateProductWithUrls::class)->handle(ProductId::of($product), [], ['en' => null]);

    expect(resolveUrl('p/leather-boots')->kind)->toBe(ResolutionKind::Found)
        ->and(resolveUrl('boots-sale')->redirect?->url)->toBe('http://localhost/p/leather-boots');
});

it('creates and updates a category with a custom URL, and its subcategories follow', function () {
    $shoes = app(CreateCategoryWithUrls::class)->handle(['name' => ['en' => 'Shoes']], ['en' => 'footwear']);
    $boots = app(CreateCategory::class)->handle(['name' => ['en' => 'Boots'], 'parent_id' => $shoes->id]);

    expect(resolveUrl('footwear/boots')->resource?->is($boots))->toBeTrue();

    app(UpdateCategoryWithUrls::class)->handle(CategoryId::of($shoes), ['name' => ['en' => 'All Shoes']], ['en' => 'all-shoes']);

    expect(resolveUrl('all-shoes/boots')->resource?->is($boots))->toBeTrue()
        ->and(resolveUrl('footwear/boots')->redirect?->url)->toBe('http://localhost/all-shoes/boots')
        ->and(Category::query()->findOrFail($shoes->id)->name->get('en'))->toBe('All Shoes');
});
