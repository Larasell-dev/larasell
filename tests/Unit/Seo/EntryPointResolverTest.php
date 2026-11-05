<?php

use Illuminate\Http\Request;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Seo\Contracts\SeoContextResolver;
use Larasell\Larasell\Seo\SeoContext;

function resolveSeoContext(string $url): ?SeoContext
{
    return app(SeoContextResolver::class)->resolve(Request::create($url));
}

/** @param array<int, array{url: string, locale: string}> $entryPoints */
function seoEntryPoints(array $entryPoints, string $channel = 'default'): void
{
    config()->set('larasell.seo.channels', [$channel => ['entry_points' => $entryPoints]]);
}

it('serves a single locale on app.url without a prefix by default', function () {
    config()->set('app.url', 'http://localhost:8000');

    $context = resolveSeoContext('http://localhost:8000/p/boots');

    expect($context->channel)->toBe('default')
        ->and($context->locale)->toBe(Locale::ENGLISH)
        ->and($context->prefix)->toBe('')
        ->and($context->url('p/boots'))->toBe('http://localhost:8000/p/boots');
});

it('prefixes every locale when a channel serves several', function () {
    config()->set('app.url', 'https://shop.com');
    config()->set('larasell.locale.supported', ['de-DE', 'en']);
    config()->set('larasell.locale.default', 'de-DE');

    expect(resolveSeoContext('https://shop.com/de-de/p/stiefel')->locale)->toBe(Locale::GERMAN_GERMANY)
        ->and(resolveSeoContext('https://shop.com/en')->locale)->toBe(Locale::ENGLISH)
        ->and(resolveSeoContext('https://shop.com/en')->url())->toBe('https://shop.com/en')
        ->and(resolveSeoContext('https://shop.com/p/boots'))->toBeNull()
        ->and(resolveSeoContext('https://shop.com/english/p/boots'))->toBeNull();
});

it('uses the locales a channel lists', function () {
    config()->set('larasell.seo.channels', [
        'b2b' => ['url' => 'https://b2b.shop.com/', 'locales' => ['de']],
    ]);

    $context = resolveSeoContext('https://b2b.shop.com/p/stiefel');

    expect($context->channel)->toBe('b2b')
        ->and($context->locale)->toBe(Locale::GERMAN)
        ->and($context->prefix)->toBe('');
});

it('serves the default locale without a prefix next to prefixed locales', function () {
    seoEntryPoints([
        ['url' => 'https://shop.com', 'locale' => 'de'],
        ['url' => 'https://shop.com/en', 'locale' => 'en'],
    ]);

    expect(resolveSeoContext('https://shop.com/en/p/boots')->locale)->toBe(Locale::ENGLISH)
        ->and(resolveSeoContext('https://shop.com/en')->locale)->toBe(Locale::ENGLISH)
        ->and(resolveSeoContext('https://shop.com/p/stiefel')->locale)->toBe(Locale::GERMAN)
        ->and(resolveSeoContext('https://shop.com/entdecken')->locale)->toBe(Locale::GERMAN)
        ->and(resolveSeoContext('https://shop.com/')->locale)->toBe(Locale::GERMAN);
});

it('serves a locale per domain', function () {
    seoEntryPoints([
        ['url' => 'https://shop.de', 'locale' => 'de-DE'],
        ['url' => 'https://shop.co.uk', 'locale' => 'en_gb'],
    ]);

    expect(resolveSeoContext('https://shop.co.uk/p/boots')->locale)->toBe(Locale::ENGLISH_UNITED_KINGDOM)
        ->and(resolveSeoContext('https://SHOP.DE/p/stiefel')->locale)->toBe(Locale::GERMAN_GERMANY)
        ->and(resolveSeoContext('https://shop.com/p/boots'))->toBeNull();
});

it('picks the longest matching prefix on a domain, across channels', function () {
    config()->set('larasell.seo.channels', [
        'ch' => ['entry_points' => [
            ['url' => 'https://shop.ch/de', 'locale' => 'de-CH'],
            ['url' => 'https://shop.ch/fr', 'locale' => 'fr-CH'],
        ]],
        'ch-pro' => ['entry_points' => [
            ['url' => 'https://shop.ch/fr/pro', 'locale' => 'fr-CH'],
        ]],
    ]);

    expect(resolveSeoContext('https://shop.ch/fr/pro/p/perceuse')->channel)->toBe('ch-pro')
        ->and(resolveSeoContext('https://shop.ch/fr/pro/p/perceuse')->url())->toBe('https://shop.ch/fr/pro')
        ->and(resolveSeoContext('https://shop.ch/fr/produits')->channel)->toBe('ch')
        ->and(resolveSeoContext('https://shop.ch/de/p/bohrer')->locale)->toBe(Locale::GERMAN_SWITZERLAND)
        ->and(resolveSeoContext('https://shop.ch/it/p/trapano'))->toBeNull();
});

it('rejects an entry point that is configured twice', function () {
    seoEntryPoints([
        ['url' => 'https://shop.com/de', 'locale' => 'de'],
        ['url' => 'https://shop.com/de/', 'locale' => 'de-DE'],
    ]);

    resolveSeoContext('https://shop.com/de');
})->throws(InvalidArgumentException::class, 'configured more than once');

it('rejects an entry point without a host', function () {
    seoEntryPoints([['url' => '/de', 'locale' => 'de']]);

    resolveSeoContext('https://shop.com/de');
})->throws(InvalidArgumentException::class, 'needs a scheme and a host');
