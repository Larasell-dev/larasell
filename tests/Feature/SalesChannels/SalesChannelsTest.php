<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\SalesChannels\Actions\AddEntryPoint;
use Larasell\Larasell\SalesChannels\Actions\CreateSalesChannel;
use Larasell\Larasell\SalesChannels\Actions\DeleteEntryPoint;
use Larasell\Larasell\SalesChannels\Actions\UpdateSalesChannel;
use Larasell\Larasell\SalesChannels\Events\EntryPointCreated;
use Larasell\Larasell\SalesChannels\Events\SalesChannelCreated;
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannels;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Settings\CurrencySettings;

beforeEach(function () {
    config()->set('larasell.locale.supported', ['en', 'de', 'fr-CH']);
    app(CurrencySettings::class)->save([Currency::EUR, Currency::CHF]);
});

function createSalesChannel(array $input = []): SalesChannel
{
    return app(CreateSalesChannel::class)->handle([
        'key' => 'ch',
        'name' => 'Switzerland',
        'locales' => ['de', 'fr-CH'],
        'currencies' => ['CHF'],
        'entry_points' => [
            ['url' => 'https://shop.ch', 'locale' => 'de'],
            ['url' => 'https://shop.ch/fr', 'locale' => 'fr-CH'],
        ],
        ...$input,
    ]);
}

it('installs a default channel from app.url, the supported locales and the enabled currencies', function () {
    $channel = SalesChannel::query()->where('key', 'default')->sole();
    $context = SalesContext::for('default', Locale::ENGLISH);

    expect($channel->type->value)->toBe('storefront')
        ->and($channel->default_locale)->toBe(Locale::ENGLISH)
        ->and($channel->default_currency)->toBe(Currency::USD)
        ->and($context->entryPoint->url())->toBe('http://localhost')
        ->and($context->currency)->toBe(Currency::USD);
});

it('creates a channel with its locales, currencies and entry points, and announces them', function () {
    Event::fake([SalesChannelCreated::class, EntryPointCreated::class]);

    $channel = createSalesChannel();

    expect($channel->default_locale)->toBe(Locale::GERMAN)
        ->and($channel->default_currency)->toBe(Currency::CHF)
        ->and(SalesContext::for('ch', Locale::FRENCH_SWITZERLAND)->url('p/bottes'))->toBe('https://shop.ch/fr/p/bottes');
    Event::assertDispatched(SalesChannelCreated::class, fn (SalesChannelCreated $event): bool => $event->channelId === $channel->id);
    Event::assertDispatchedTimes(EntryPointCreated::class, 2);
});

it('checks a channel before saving it', function (array $input, string $message) {
    expect(fn () => createSalesChannel($input))->toThrow(InvalidSalesChannel::class, $message)
        ->and(SalesChannel::query()->where('key', 'ch')->exists())->toBeFalse();
})->with([
    'unsupported locale' => [['locales' => ['de', 'it']], 'not a supported locale'],
    'currency not enabled' => [['currencies' => ['CHF', 'USD']], 'not enabled'],
    'default locale outside its locales' => [['default_locale' => 'en'], 'default locale'],
    'default currency outside its currencies' => [['default_currency' => 'EUR'], 'default currency'],
    'entry point in another locale' => [['entry_points' => [['url' => 'https://shop.ch/en', 'locale' => 'en']]], 'locale [en]'],
    'entry point taken by another channel' => [['entry_points' => [['url' => 'http://localhost', 'locale' => 'de']]], 'already'],
]);

it('makes the first entry point per locale and currency the primary one, and the next one when it goes', function () {
    $channel = createSalesChannel();
    $www = app(AddEntryPoint::class)->handle($channel->id, ['url' => 'https://www.shop.ch', 'locale' => 'de']);

    expect(SalesContext::for('ch', Locale::GERMAN)->entryPoint->host)->toBe('shop.ch');

    app(DeleteEntryPoint::class)->handle(SalesContext::for('ch', Locale::GERMAN)->entryPoint->id);

    expect(SalesContext::for('ch', Locale::GERMAN)->entryPoint->id)->toBe($www->id);
});

it('finds the entry point of a request by host and the longest prefix', function () {
    createSalesChannel();
    $resolve = fn (string $url): ?SalesContext => app(SalesChannels::class)->resolve(Request::create($url));

    expect($resolve('https://shop.ch/fr/p/bottes')?->locale)->toBe(Locale::FRENCH_SWITZERLAND)
        ->and($resolve('https://shop.ch/fr')?->locale)->toBe(Locale::FRENCH_SWITZERLAND)
        ->and($resolve('https://shop.ch/frites')?->locale)->toBe(Locale::GERMAN)
        ->and($resolve('https://SHOP.ch/p/stiefel')?->channel)->toBe('ch')
        ->and($resolve('https://elsewhere.ch/p/stiefel'))->toBeNull();
});

it('sees a new entry point at once, although entry points are cached', function () {
    $channel = createSalesChannel();
    $resolve = fn (string $url): ?SalesContext => app(SalesChannels::class)->resolve(Request::create($url));

    expect($resolve('https://shop.ch/it'))->not->toBeNull()->and($resolve('https://shop.ch/it')->locale)->toBe(Locale::GERMAN);

    app(UpdateSalesChannel::class)->handle($channel->id, ['locales' => ['de', 'fr-CH', 'en']]);
    app(AddEntryPoint::class)->handle($channel->id, ['url' => 'https://shop.ch/it', 'locale' => 'en']);

    expect($resolve('https://shop.ch/it')->locale)->toBe(Locale::ENGLISH);
});

it('stores the sales context with a cart and an order, and falls back to the primary entry point', function () {
    $channel = createSalesChannel();
    $fr = SalesContext::for('ch', Locale::FRENCH_SWITZERLAND);
    Context::addHidden(SalesContext::KEY, $fr);

    $cart = Cart::query()->create();
    $second = app(AddEntryPoint::class)->handle($channel->id, ['url' => 'https://fr.shop.ch', 'locale' => 'fr-CH']);

    expect($cart->sales_channel_id)->toBe($channel->id)
        ->and($cart->locale)->toBe('fr-CH')
        ->and($cart->currency)->toBe(Currency::CHF)
        ->and($cart->fresh()->sales_context)->toEqual($fr);

    app(DeleteEntryPoint::class)->handle($fr->entryPoint->id);

    expect($cart->fresh()->entry_point_id)->toBeNull()
        ->and($cart->fresh()->sales_context->entryPoint->id)->toBe($second->id);

    // A context read before does not overwrite a later change of its columns.
    $cart->sales_context;
    $cart->update(['currency' => Currency::EUR]);
    SalesContext::for('ch', Locale::FRENCH_SWITZERLAND)->activate();
    $euro = Cart::query()->create(['currency' => Currency::EUR]);

    expect($cart->fresh()->currency)->toBe(Currency::EUR)
        ->and($euro->fresh()->currency)->toBe(Currency::EUR);
});
