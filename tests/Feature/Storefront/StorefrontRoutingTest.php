<?php

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\Actions\CreateSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Settings\CurrencySettings;
use Larasell\Larasell\Storefront\Actions\AddEntryPoint;
use Larasell\Larasell\Storefront\StorefrontContext;

beforeEach(function () {
    config()->set('larasell.locale.supported', ['en', 'de']);
    app(CurrencySettings::class)->save([Currency::EUR, Currency::USD]);
    app('translator')->addLines(['routes.cart' => 'warenkorb'], 'de');
    $shop = app(CreateSalesChannel::class)->handle([
        'key' => 'shop',
        'name' => 'Shop',
        'type' => 'storefront',
        'locales' => ['de', 'en'],
        'currencies' => ['EUR'],
    ]);
    app(AddEntryPoint::class)->handle(SalesChannelId::of($shop), ['url' => 'https://shop.de', 'locale' => 'de']);
    app(AddEntryPoint::class)->handle(SalesChannelId::of($shop), ['url' => 'https://shop.de/en', 'locale' => 'en']);
});

function shopRoutes(): void
{
    Route::storefront(function () {
        Route::get('/', fn (SalesContext $context, StorefrontContext $storefront) => "home {$context->channel} {$context->locale->value} ".app()->getLocale().' '.$storefront->url());
        Route::get('cart', fn () => 'cart '.storefront_route('cart'))->name('cart')->localized();
        Route::get('orders/{order}', fn (string $order) => 'order '.$order)->name('order');
    });
}

final class RecordSalesContext implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function handle(): void
    {
        cache()->put('recorded-context', [StorefrontContext::current()?->url(), SalesContext::current()?->locale->value]);
    }
}

it('serves the routes of every entry point from one copy per language', function () {
    shopRoutes();

    $this->get('https://shop.de/')->assertOk()->assertSee('home shop de de https://shop.de');
    $this->get('https://shop.de/en')->assertOk()->assertSee('home shop en en https://shop.de/en');
    $this->get('https://shop.de/warenkorb')->assertOk()->assertSee('cart https://shop.de/warenkorb');
    $this->get('https://shop.de/en/cart')->assertOk()->assertSee('cart https://shop.de/en/cart');
    $this->get('https://shop.de/cart')->assertNotFound();
    $this->get('https://shop.de/en/warenkorb')->assertNotFound();
    $this->get('https://elsewhere.de/')->assertNotFound();

    expect(collect(Route::getRoutes()->getRoutes())->map->getName()->filter()->sort()->values()->all())
        ->toBe(['de.cart', 'de.order', 'en.cart', 'en.order']);
});

it('serves a new entry point without registering routes again, also from cached routes', function () {
    shopRoutes();
    $router = app('router');
    $routes = $router->getRoutes();
    $routes->refreshNameLookups();
    $routes->refreshActionLookups();
    foreach ($routes as $route) {
        $route->prepareForSerialization();
    }
    $router->setCompiledRoutes(eval('return '.var_export($routes->compile(), true).';'));

    app(AddEntryPoint::class)->handle(SalesChannelId::of(SalesChannel::query()->where('key', 'shop')->sole()), [
        'url' => 'https://shop.at/de', 'locale' => 'de',
    ]);

    $this->get('https://shop.at/de/warenkorb')->assertOk()->assertSee('cart https://shop.at/de/warenkorb');
    $this->get('https://shop.de/en/cart')->assertOk()->assertSee('cart https://shop.de/en/cart');
    $this->get('https://shop.de/~de/warenkorb')->assertNotFound();
});

it('leaves routes outside the storefront on the same host alone', function () {
    shopRoutes();
    Route::middleware('web')->get('admin/orders/{order}', fn (string $order) => 'admin '.$order);

    Route::middleware('web')->get('admin/{any}', fn () => 'admin not found')->where('any', '.*')->fallback();
    Route::fallback(fn () => 'app not found');

    $this->get('https://shop.de/admin/orders/A-1')->assertOk()->assertSee('admin A-1');
    // The admin's own catch-all answers below admin/; the app's root one leaves the storefront alone.
    $this->get('https://shop.de/admin/missing')->assertSee('admin not found');
    $this->get('https://shop.de/orders/A-1')->assertOk()->assertSee('order A-1');
    $this->get('https://shop.de/warenkorb')->assertOk()->assertSee('cart https://shop.de/warenkorb');
});

it('builds storefront URLs for any context, and refuses without one', function () {
    shopRoutes();
    $en = StorefrontContext::for('shop', Locale::ENGLISH);
    Context::forgetHidden(StorefrontContext::KEY);

    expect(storefront_route('order', ['order' => 'A-1'], $en))->toBe('https://shop.de/en/orders/A-1')
        ->and(storefront_route('cart', [], StorefrontContext::for('shop', Locale::GERMAN)))->toBe('https://shop.de/warenkorb')
        ->and(fn () => storefront_route('cart'))->toThrow(LogicException::class, 'StorefrontContext');
});

it('carries the sales context of a request into the jobs it queues', function () {
    config()->set('queue.default', 'database');
    Schema::create('jobs', function ($table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    Route::storefront(fn () => Route::get('checkout', function () {
        RecordSalesContext::dispatch();

        return 'queued';
    }));

    $this->get('https://shop.de/en/checkout')->assertOk();
    Context::flush();
    $this->artisan('queue:work', ['--once' => true, '--queue' => 'default'])->assertSuccessful();

    expect(cache()->get('recorded-context'))->toBe(['https://shop.de/en', 'en']);
});
