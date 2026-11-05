<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\SalesChannels\Actions\CreateSalesChannel;
use Larasell\Larasell\SalesChannels\Actions\UpdateSalesChannel;
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelTypes;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Settings\CurrencySettings;
use Larasell\Larasell\Storefront\Actions\AddEntryPoint;
use Larasell\Larasell\Storefront\Actions\DeleteEntryPoint;
use Larasell\Larasell\Storefront\EntryPoint;
use Larasell\Larasell\Storefront\Events\EntryPointCreated;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Storefront\Storefronts;

beforeEach(function () {
    config()->set('larasell.locale.supported', ['en', 'de', 'fr-CH']);
    app(CurrencySettings::class)->save([Currency::EUR, Currency::CHF]);
});

function swissStorefront(): SalesChannel
{
    $channel = app(CreateSalesChannel::class)->handle([
        'key' => 'ch', 'name' => 'Switzerland', 'type' => 'storefront', 'locales' => ['de', 'fr-CH'], 'currencies' => ['CHF'],
    ]);
    app(AddEntryPoint::class)->handle($channel->id, ['url' => 'https://shop.ch', 'locale' => 'de']);
    app(AddEntryPoint::class)->handle($channel->id, ['url' => 'https://shop.ch/fr', 'locale' => 'fr-CH']);

    return $channel;
}

it('reaches a storefront channel through its entry points, and announces each', function () {
    Event::fake([EntryPointCreated::class]);

    swissStorefront();
    $fr = StorefrontContext::for('ch', Locale::FRENCH_SWITZERLAND);

    expect($fr->sales)->toEqual(SalesContext::for('ch', 'fr-CH'))
        ->and($fr->url('p/bottes'))->toBe('https://shop.ch/fr/p/bottes')
        ->and(StorefrontContext::for('default', 'en')->url())->toBe('http://localhost');
    Event::assertDispatchedTimes(EntryPointCreated::class, 2);
});

it('checks an entry point before saving it', function (array $input, string $message) {
    $channel = swissStorefront();

    expect(fn () => app(AddEntryPoint::class)->handle($channel->id, $input))->toThrow(InvalidSalesChannel::class, $message);
})->with([
    'locale the channel lacks' => [['url' => 'https://shop.ch/en', 'locale' => 'en'], 'locale [en]'],
    'currency the channel lacks' => [['url' => 'https://shop.ch/eur', 'locale' => 'de', 'currency' => 'EUR'], 'currency [EUR]'],
    'taken by another channel' => [['url' => 'http://localhost', 'locale' => 'de'], 'already'],
    'no host' => [['url' => '/de', 'locale' => 'de'], 'scheme and a host'],
]);

it('gives entry points only to storefront channels', function () {
    app(SalesChannelTypes::class)->register('pos');
    $pos = app(CreateSalesChannel::class)->handle(['key' => 'pos', 'name' => 'Shop floor', 'type' => 'pos', 'locales' => ['de'], 'currencies' => ['CHF']]);
    $entryPoint = new EntryPoint(99, $pos->id, 'https', 'pos.ch', null, '', Locale::GERMAN, Currency::CHF, true);

    expect(fn () => app(AddEntryPoint::class)->handle($pos->id, ['url' => 'https://pos.ch', 'locale' => 'de']))
        ->toThrow(InvalidSalesChannel::class, 'not a storefront')
        ->and(fn () => new StorefrontContext(SalesContext::for('pos', 'de'), $entryPoint))->toThrow(LogicException::class, 'not a storefront')
        ->and(fn () => StorefrontContext::for('pos', 'de'))->toThrow(LogicException::class, 'not a storefront');
});

it('refuses an entry point of another channel or locale in a storefront context', function () {
    swissStorefront();
    $de = StorefrontContext::for('ch', 'de');

    expect(fn () => new StorefrontContext(SalesContext::for('default', 'en'), $de->entryPoint))->toThrow(LogicException::class, 'belongs to')
        ->and(fn () => new StorefrontContext(SalesContext::for('ch', 'fr-CH'), $de->entryPoint))->toThrow(LogicException::class, 'serves [de, CHF]');
});

it('refuses unknown channel types', function () {
    app(CreateSalesChannel::class)->handle(['key' => 'x', 'name' => 'X', 'type' => 'carrier-pigeon', 'locales' => ['de'], 'currencies' => ['CHF']]);
})->throws(InvalidSalesChannel::class, 'no sales channel type [carrier-pigeon]');

it('makes the first entry point per locale and currency the primary one, and the next one when it goes', function () {
    $channel = swissStorefront();
    $www = app(AddEntryPoint::class)->handle($channel->id, ['url' => 'https://www.shop.ch', 'locale' => 'de']);

    expect(StorefrontContext::for('ch', 'de')->entryPoint->host)->toBe('shop.ch');

    app(DeleteEntryPoint::class)->handle(StorefrontContext::for('ch', 'de')->entryPoint->id);

    expect(StorefrontContext::for('ch', 'de')->entryPoint->id)->toBe($www->id);
});

it('finds the entry point of a request by host and the longest prefix', function () {
    swissStorefront();
    $resolve = fn (string $url): ?StorefrontContext => app(Storefronts::class)->resolve(Request::create($url));

    expect($resolve('https://shop.ch/fr/p/bottes')?->sales->locale)->toBe(Locale::FRENCH_SWITZERLAND)
        ->and($resolve('https://shop.ch/fr')?->sales->locale)->toBe(Locale::FRENCH_SWITZERLAND)
        ->and($resolve('https://shop.ch/frites')?->sales->locale)->toBe(Locale::GERMAN)
        ->and($resolve('https://SHOP.ch/p/stiefel')?->sales->channel)->toBe('ch')
        ->and($resolve('https://elsewhere.ch/p/stiefel'))->toBeNull();
});

it('reaches no inactive channel', function () {
    $channel = swissStorefront();

    app(UpdateSalesChannel::class)->handle($channel->id, ['active' => false]);

    expect(app(Storefronts::class)->resolve(Request::create('https://shop.ch/p/stiefel')))->toBeNull();
});

it('sees a new entry point at once, although entry points are cached', function () {
    $channel = swissStorefront();
    $resolve = fn (string $url): ?StorefrontContext => app(Storefronts::class)->resolve(Request::create($url));

    expect($resolve('https://shop.ch/it')?->sales->locale)->toBe(Locale::GERMAN);

    app(UpdateSalesChannel::class)->handle($channel->id, ['locales' => ['de', 'fr-CH', 'en']]);
    app(AddEntryPoint::class)->handle($channel->id, ['url' => 'https://shop.ch/it', 'locale' => 'en']);

    expect($resolve('https://shop.ch/it')?->sales->locale)->toBe(Locale::ENGLISH);
});

it('keeps a channel from dropping a locale or currency its entry points still serve', function () {
    $channel = swissStorefront();

    expect(fn () => app(UpdateSalesChannel::class)->handle($channel->id, ['locales' => ['de']]))
        ->toThrow(InvalidSalesChannel::class, 'still serves [fr-CH')
        ->and(SalesContext::for('ch', 'fr-CH')->locale)->toBe(Locale::FRENCH_SWITZERLAND);
});

it('links a cart or order back through the primary entry point of its channel and locale', function () {
    swissStorefront();
    $cart = Cart::createIn(SalesContext::for('ch', 'fr-CH'));

    expect(StorefrontContext::for($cart->sales_context)->url('panier'))->toBe('https://shop.ch/fr/panier');
});
