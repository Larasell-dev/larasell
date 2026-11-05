<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Price;
use Larasell\Larasell\SalesChannels\Actions\CreateSalesChannel;
use Larasell\Larasell\SalesChannels\Events\SalesChannelCreated;
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
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
        'type' => 'storefront',
        'locales' => ['de', 'fr-CH'],
        'currencies' => ['CHF', 'EUR'],
        ...$input,
    ]);
}

it('installs a default storefront channel from the supported locales and the enabled currencies', function () {
    $channel = SalesChannel::query()->where('key', 'default')->sole();
    $context = SalesContext::for('default', Locale::ENGLISH);

    expect($channel->type)->toBe('storefront')
        ->and($channel->default_locale)->toBe(Locale::ENGLISH)
        ->and($channel->default_currency)->toBe(Currency::USD)
        ->and($context->channelId)->toBe($channel->id)
        ->and($context->currency)->toBe(Currency::USD);
});

it('creates a channel with its locales and currencies, and announces it', function () {
    Event::fake([SalesChannelCreated::class]);

    $channel = createSalesChannel();

    expect($channel->default_locale)->toBe(Locale::GERMAN)
        ->and($channel->default_currency)->toBe(Currency::CHF)
        ->and(SalesContext::for('ch', 'fr-CH', Currency::EUR))->toEqual(new SalesContext('ch', $channel->id, Locale::FRENCH_SWITZERLAND, Currency::EUR));
    Event::assertDispatched(SalesChannelCreated::class, fn (SalesChannelCreated $event): bool => $event->channelId === $channel->id);
});

it('checks a channel before saving it', function (array $input, string $message) {
    expect(fn () => createSalesChannel($input))->toThrow(InvalidSalesChannel::class, $message)
        ->and(SalesChannel::query()->where('key', 'ch')->exists())->toBeFalse();
})->with([
    'unsupported locale' => [['locales' => ['de', 'it']], 'not a supported locale'],
    'currency not enabled' => [['currencies' => ['CHF', 'USD']], 'not enabled'],
    'default locale outside its locales' => [['default_locale' => 'en'], 'default locale'],
    'default currency outside its currencies' => [['currencies' => ['CHF'], 'default_currency' => 'EUR'], 'default currency'],
]);

it('gives a context only in a locale and currency of the channel', function (Closure $context, string $message) {
    createSalesChannel();

    expect($context)->toThrow(InvalidSalesChannel::class, $message);
})->with([
    'unknown channel' => [fn () => SalesContext::for('nowhere', 'de'), 'no sales channel [nowhere]'],
    'locale of another channel' => [fn () => SalesContext::for('ch', 'en'), 'does not sell in [en]'],
    'currency it does not take' => [fn () => SalesContext::for('ch', 'de', Currency::USD), 'does not sell in [USD]'],
]);

it('sells through a channel that has no URL at all', function () {
    $channel = createSalesChannel(['key' => 'pos', 'name' => 'Shop floor']);
    $context = SalesContext::for('pos', Locale::GERMAN);
    $product = app(CreateProduct::class)->handle([
        'name' => ['de' => 'Stiefel'], 'price' => Price::of(1000), 'stock' => 5,
    ]);

    $cart = Cart::createIn($context);
    $cart->add($product, 1);
    $order = app(Checkout::class)->create($cart, ['customer_email' => 'a@example.com', 'customer_name' => 'A'])->order;

    expect($cart->fresh()->sales_context)->toEqual($context)
        ->and($order->fresh()->sales_context)->toEqual($context)
        ->and($order->sales_channel_id)->toBe($channel->id)
        ->and(Schema::hasColumn('larasell_carts', 'entry_point_id'))->toBeFalse()
        ->and(Schema::hasColumn('larasell_orders', 'entry_point_id'))->toBeFalse();
});

it('keeps a cart in its own currency, also after its context was read', function () {
    createSalesChannel();
    $fr = SalesContext::for('ch', 'fr-CH');

    $cart = Cart::createIn($fr);
    $cart->sales_context;
    $cart->update(['currency' => Currency::EUR]);
    $euro = Cart::createIn($fr, ['currency' => Currency::EUR]);

    expect($cart->fresh()->currency)->toBe(Currency::EUR)
        ->and($cart->fresh()->sales_context->currency)->toBe(Currency::EUR)
        ->and($euro->fresh()->currency)->toBe(Currency::EUR);
});

it('creates a cart only in a context it is given, not in one that happens to be current', function () {
    createSalesChannel();
    SalesContext::for('ch', 'de')->activate();

    expect(fn () => Cart::query()->create())->toThrow(LogicException::class, 'Cart::createIn(')
        ->and(Cart::createIn(SalesContext::for('default', 'en'))->fresh()->sales_context->channel)->toBe('default');
});
