<?php

use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Settings\LocaleSettings;

it('supports only English by default', function () {
    expect(app(LocaleSettings::class)->supported())->toBe([Locale::ENGLISH])
        ->and(app(LocaleSettings::class)->default())->toBe(Locale::ENGLISH);
});

it('reads configured locales in canonical form', function () {
    config()->set('larasell.locale.supported', ['en', 'de_de', 'zh-hans']);
    config()->set('larasell.locale.default', 'DE-de');

    expect(app(LocaleSettings::class)->supported())->toBe([Locale::ENGLISH, Locale::GERMAN_GERMANY, Locale::CHINESE_SIMPLIFIED])
        ->and(app(LocaleSettings::class)->default())->toBe(Locale::GERMAN_GERMANY);
});

it('rejects a default locale that is not supported', function () {
    config()->set('larasell.locale.supported', ['en']);
    config()->set('larasell.locale.default', 'de');

    app(LocaleSettings::class)->default();
})->throws(InvalidArgumentException::class, 'must be one of larasell.locale.supported');

it('rejects an unknown supported locale', function () {
    config()->set('larasell.locale.supported', ['en', 'xx']);

    app(LocaleSettings::class)->supported();
})->throws(InvalidArgumentException::class);

it('negotiates the locale from an Accept-Language header', function (?string $header, Locale $expected) {
    config()->set('larasell.locale.supported', ['en', 'de', 'fr-CA', 'zh-Hans']);
    config()->set('larasell.locale.default', 'en');

    expect(app(LocaleSettings::class)->negotiate($header))->toBe($expected);
})->with([
    'no header' => [null, Locale::ENGLISH],
    'exact match' => ['de', Locale::GERMAN],
    'region falls back to its language' => ['de-AT', Locale::GERMAN],
    'language matches a regional locale' => ['fr', Locale::FRENCH_CANADA],
    'script and region fall back to the script' => ['zh-Hans-CN', Locale::CHINESE_SIMPLIFIED],
    'quality outranks order' => ['fr-CA;q=0.5, de;q=0.9', Locale::GERMAN],
    'equal quality keeps client order' => ['fr-CA, de', Locale::FRENCH_CANADA],
    'unsupported ranges are skipped' => ['es-ES, it;q=0.9, de;q=0.8', Locale::GERMAN],
    'zero quality excludes a range' => ['de;q=0, en;q=0.5', Locale::ENGLISH],
    'case and underscores are tolerated' => ['DE_at', Locale::GERMAN],
    'wildcard means the default' => ['es, *;q=0.5, de;q=0.1', Locale::ENGLISH],
    'nothing supported' => ['es, it', Locale::ENGLISH],
    'malformed header' => ['%%%, ;q=1', Locale::ENGLISH],
]);
