<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateCategoryPayload;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Exceptions\SeoPageGone;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SeoUrlManager;
use Larasell\Larasell\Translatable;

beforeEach(function () {
    config()->set('queue.default', 'sync');
});

/** @param  array<string, string>  $name */
function routedProduct(array|string $name, Visibility $status = Visibility::Visible): Product
{
    return app(CreateProduct::class)->handle([
        'name' => is_string($name) ? ['en' => $name] : $name,
        'price' => Price::of(1000),
        'status' => $status,
    ]);
}

function routedCategory(string $name, ?Category $parent = null, Visibility $status = Visibility::Visible): Category
{
    return app(CreateCategory::class)->handle(new CreateCategoryPayload(
        name: new Translatable(['en' => $name]),
        status: $status,
        parentId: $parent === null ? null : CategoryId::of($parent),
    ));
}

function storefrontRoutes(): void
{
    Route::storefront(function () {
        Route::get('/', fn (SalesContext $context) => 'home '.$context->locale->value);
        Route::get('cart', fn () => 'cart');
        Route::get('p/{product}', fn (Product $product) => 'product '.$product->name->get().' '.app()->getLocale());
        Route::seo([
            Category::class => fn (Category $category) => 'category '.$category->name->get(),
            Product::class => fn (Product $product) => 'any product '.$product->name->get(),
        ]);
    });
}

function moveSeoUrl(Product|Category $resource, string $path): void
{
    app(SeoUrlManager::class)->setCustom($resource, app(SeoUrlManager::class)->contexts()[0], $path);
}

it('shows a product on its typed route', function () {
    storefrontRoutes();
    routedProduct('Leather Boots');

    $this->get('/p/leather-boots')->assertOk()->assertSee('product Leather Boots en');
});

it('redirects an old URL to the current one and keeps the query string', function () {
    storefrontRoutes();
    Route::storefront(fn () => Route::post('p/{product}', fn (Product $product) => 'added'));
    $product = routedProduct('Boots');
    updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);

    $this->get('/p/boots?utm_source=mail')->assertStatus(301)->assertRedirect('http://localhost/p/leather-boots?utm_source=mail');
    $this->post('/p/boots')->assertStatus(308);
});

it('redirects paths that are not normalized', function (string $path) {
    storefrontRoutes();
    routedProduct('Boots');

    $this->get($path)->assertStatus(301)->assertRedirect('http://localhost/p/boots');
})->with(['/P/Boots', '/p//boots', 'encoded slash' => ['/p/boots%2F']]);

it('redirects a trailing slash, which the test client would strip', function () {
    storefrontRoutes();
    routedProduct('Boots');

    $response = app(Kernel::class)->handle(Request::create('http://localhost/p/boots/'));

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe('http://localhost/p/boots');
});

it('answers 404 for a hidden product until it is published again', function () {
    storefrontRoutes();
    $product = routedProduct('Boots');
    updateCatalog($product, ['name' => ['en' => 'Leather Boots']]);
    updateCatalog($product, ['status' => Visibility::Hidden]);

    $this->get('/p/leather-boots')->assertNotFound();
    $this->get('/p/boots')->assertNotFound();

    updateCatalog($product, ['status' => Visibility::Visible]);

    $this->get('/p/leather-boots')->assertOk();
});

it('answers 410 for a deleted product, which the app can render', function () {
    storefrontRoutes();
    deleteFromCatalog(routedProduct('Boots'));

    $this->get('/p/boots')->assertStatus(410);

    app(ExceptionHandler::class)->renderable(
        fn (SeoPageGone $exception) => response('discontinued '.$exception->snapshot['name'], 410),
    );

    $this->get('/p/boots')->assertStatus(410)->assertSee('discontinued Boots');
});

it('redirects a deleted product to its replacement while that is available', function () {
    storefrontRoutes();
    $old = routedProduct('Boots');
    $new = routedProduct('Boots 2027');
    app(SeoUrlManager::class)->markGone($old, $new);

    $this->get('/p/boots')->assertStatus(301)->assertRedirect('http://localhost/p/boots-2027');

    updateCatalog($new, ['status' => Visibility::Hidden]);

    $this->get('/p/boots')->assertStatus(410);
});

it('redirects a deleted category to its parent while the parent is available', function () {
    storefrontRoutes();
    $shoes = routedCategory('Shoes');
    deleteFromCatalog(routedCategory('Boots', $shoes));

    $this->get('/shoes/boots')->assertStatus(302)->assertRedirect('http://localhost/shoes');

    updateCatalog($shoes, ['status' => Visibility::Hidden]);

    $this->get('/shoes/boots')->assertStatus(410);
});

it('shows every kind of page through the catch-all, including custom URLs', function () {
    storefrontRoutes();
    $shoes = routedCategory('Shoes');
    routedCategory('Boots', $shoes);
    $sale = routedProduct('Sale Boots');
    moveSeoUrl($sale, 'winter-sale');

    $this->get('/shoes/boots')->assertOk()->assertSee('category Boots');
    $this->get('/winter-sale')->assertOk()->assertSee('any product Sale Boots');
    $this->get('/p/sale-boots')->assertRedirect('http://localhost/winter-sale');
    $this->get('/nothing-here')->assertNotFound();
});

it('shows only its own kind of page on a typed route', function () {
    Route::storefront(fn () => Route::get('c/{category}', fn (Category $category) => 'category')->where('category', '.*'));
    routedProduct('Boots');

    $this->get('/c/p/boots')->assertNotFound();
});

it('leaves routes outside the storefront binding by ID', function () {
    storefrontRoutes();
    Route::middleware('web')->get('admin/products/{product}', fn (Product $product) => 'admin '.$product->getKey());
    $product = routedProduct('Boots');

    $this->get('/admin/products/'.$product->getKey())->assertOk()->assertSee('admin '.$product->getKey());
    $this->get('/p/'.$product->getKey())->assertNotFound();
});

it('serves each locale below its prefix', function () {
    shopLocales(['en', 'de-DE']);
    storefrontRoutes();
    routedProduct(['en' => 'Boots', 'de' => 'Stiefel']);

    $this->get('/de-de/p/stiefel')->assertOk()->assertSee('product Stiefel de-DE');
    $this->get('/en/p/boots')->assertOk()->assertSee('product Boots en');
    $this->get('/de-de')->assertOk()->assertSee('home de-DE');
    $this->get('/de-de/p/boots')->assertNotFound();
    $this->get('/p/boots')->assertNotFound();
});

it('serves the default locale without a prefix next to prefixed ones', function () {
    shopLocales(['en', 'de'], [
        ['url' => 'http://localhost', 'locale' => 'de'],
        ['url' => 'http://localhost/en', 'locale' => 'en'],
    ]);
    storefrontRoutes();
    routedProduct(['en' => 'Boots', 'de' => 'Stiefel']);

    $this->get('/p/stiefel')->assertOk()->assertSee('product Stiefel de');
    $this->get('/en/p/boots')->assertOk()->assertSee('product Boots en');
    $this->get('/en')->assertOk()->assertSee('home en');
    $this->get('/')->assertOk()->assertSee('home de');
});

it('answers 404 on a host without an entry point', function () {
    storefrontRoutes();
    routedProduct('Boots');

    $this->get('http://elsewhere.test/p/boots')->assertNotFound();
});

it('keeps static storefront routes free of pages', function () {
    storefrontRoutes();

    expect(SeoUrl::query()->where('resource_id', routedCategory('Cart')->getKey())->value('path'))->toBe('cart-2');
    $this->get('/cart')->assertOk()->assertSee('cart');
});

it('refuses Route::seo() outside the storefront group', function () {
    Route::middleware('web')->group(fn () => Route::seo([Product::class => fn () => 'product']));
})->throws(LogicException::class, 'inside Route::storefront()');

it('leaves the path of other storefront routes as sent', function () {
    Route::storefront(fn () => Route::get('orders/{order}', fn (string $order) => 'order '.$order));

    $this->get('/orders/A-1001')->assertOk()->assertSee('order A-1001');
});

it('refuses a Route::seo() key that no resource type handles', function () {
    Route::storefront(fn () => Route::seo([ProductAttribute::class => fn () => 'attribute']));
})->throws(LogicException::class, 'no SEO resource type');

it('refuses a Route::seo() controller whose page parameter does not accept its key', function () {
    Route::storefront(fn () => Route::seo([Product::class => fn (Category $category) => 'category']));
})->throws(LogicException::class, 'cannot receive');

it('hands the page to a parameter typed with any class it is an instance of', function () {
    Route::storefront(fn () => Route::seo([Product::class => fn (Model $page) => 'page '.$page->getKey()]));
    $product = routedProduct('Boots');

    $this->get('/p/boots')->assertOk()->assertSee('page '.$product->getKey());
});
