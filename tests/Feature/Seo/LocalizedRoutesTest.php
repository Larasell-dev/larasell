<?php

use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Catalog\Actions\CreateCategory;
use Larasell\Larasell\Catalog\Actions\CreateCategoryPayload;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Translatable;

beforeEach(function () {
    config()->set('queue.default', 'sync');
    shopLocales(['en', 'de'], [
        ['url' => 'http://localhost', 'locale' => 'de'],
        ['url' => 'http://localhost/en', 'locale' => 'en'],
    ]);
    app('translator')->addLines([
        'routes.cart' => 'warenkorb',
        'routes.cart/add' => 'warenkorb/hinzufuegen',
        'routes.cart/remove/{item}' => 'warenkorb/{item}/entfernen',
    ], 'de');
});

function localizedRoutes(): void
{
    Route::storefront(function () {
        Route::get('cart', fn () => 'cart '.app()->getLocale())->name('cart')->localized();
        Route::post('cart/add', fn () => 'added')->name('cart.add')->localized();
        Route::delete('cart/remove/{item}', fn (string $item) => 'removed '.$item)->localized();
        Route::get('checkout', fn () => 'checkout '.app()->getLocale())->localized();
    });
}

it('uses the exact translation of a path, and the path as written without one', function () {
    localizedRoutes();

    $this->get('/warenkorb')->assertOk()->assertSee('cart de');
    $this->get('/en/cart')->assertOk()->assertSee('cart en');
    $this->post('/warenkorb/hinzufuegen')->assertOk()->assertSee('added');
    $this->delete('/warenkorb/7/entfernen')->assertOk()->assertSee('removed 7');
    $this->delete('/en/cart/remove/7')->assertOk()->assertSee('removed 7');
    $this->get('/checkout')->assertOk()->assertSee('checkout de');
    $this->get('/en/checkout')->assertOk()->assertSee('checkout en');
});

it('serves each translated path only in its own locale', function () {
    localizedRoutes();

    $this->get('/cart')->assertNotFound();
    $this->get('/en/warenkorb')->assertNotFound();
});

it('names each copy by locale', function () {
    localizedRoutes();

    expect(storefront_route('cart', [], StorefrontContext::for('default', 'de')))->toBe('http://localhost/warenkorb')
        ->and(storefront_route('cart', [], StorefrontContext::for('default', 'en')))->toBe('http://localhost/en/cart')
        ->and(Route::has(['de.cart', 'en.cart', 'de.cart.add']))->toBeTrue();
});

it('reserves translated paths only in their locale', function () {
    localizedRoutes();
    $category = app(CreateCategory::class)->handle(new CreateCategoryPayload(new Translatable(['de' => 'Warenkorb', 'en' => 'Warenkorb'])), origin());

    expect(SeoUrl::query()->where('resource_id', $category->getKey())->pluck('path', 'locale')->all())
        ->toBe(['de' => 'warenkorb-2', 'en' => 'warenkorb']);
});

it('refuses ->localized() outside the storefront group', function () {
    Route::get('cart', fn () => 'cart')->localized();
})->throws(LogicException::class, 'inside Route::storefront()');

it('refuses a translation that drops a parameter', function () {
    app('translator')->addLines(['routes.orders/{order}' => 'bestellungen'], 'de');

    Route::storefront(fn () => Route::get('orders/{order}', fn () => 'order')->localized());
})->throws(InvalidArgumentException::class, 'must keep its parameters');

it('keeps translated paths in cached routes', function () {
    localizedRoutes();
    $router = app('router');

    // What route:cache writes and the cached app loads.
    $routes = $router->getRoutes();
    $routes->refreshNameLookups();
    $routes->refreshActionLookups();
    foreach ($routes as $route) {
        $route->prepareForSerialization();
    }
    $compiled = eval('return '.var_export($routes->compile(), true).';');
    $router->setCompiledRoutes($compiled);

    $this->get('/warenkorb')->assertOk()->assertSee('cart de');
    $this->delete('/warenkorb/7/entfernen')->assertOk()->assertSee('removed 7');
    $this->get('/en/cart')->assertOk()->assertSee('cart en');
    $this->get('/cart')->assertNotFound();
    $this->get('/en/warenkorb')->assertNotFound();
    expect(storefront_route('cart.add', [], StorefrontContext::for('default', 'de')))->toBe('http://localhost/warenkorb/hinzufuegen');
});
