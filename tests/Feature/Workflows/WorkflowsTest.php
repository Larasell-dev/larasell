<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Actions\CreateProductPayload;
use Larasell\Larasell\Catalog\Exceptions\ProductInUse;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Price;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\ResolutionKind;
use Larasell\Larasell\Seo\SeoPages;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Workflows\DiscontinueProduct;

function workflowProduct(string $name, int $stock = 0): Product
{
    return app(CreateProduct::class)->handle(new CreateProductPayload([
        'name' => ['en' => $name],
        'price' => Price::of(1000),
        'stock' => $stock,
        'status' => Visibility::Visible,
    ]));
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
