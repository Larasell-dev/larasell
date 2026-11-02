<?php

use Illuminate\Support\Facades\App;
use Larasell\Larasell\Translatable;

it('resolves translations using the laravel locale', function () {
    App::setLocale('de');

    $title = new Translatable([
        'en' => 'Desk lamp',
        'de' => 'Schreibtischlampe',
    ]);

    expect($title->get())->toBe('Schreibtischlampe');
});

it('resolves translations using an explicit locale', function () {
    $title = new Translatable([
        'en' => 'Desk lamp',
        'de' => 'Schreibtischlampe',
    ]);

    expect($title->get('en'))->toBe('Desk lamp');
});

it('returns all translations', function () {
    $title = new Translatable([
        'en' => 'Desk lamp',
        'de' => 'Schreibtischlampe',
    ]);

    expect($title->all())->toBe([
        'en' => 'Desk lamp',
        'de' => 'Schreibtischlampe',
    ]);
});

it('falls back to the base locale', function () {
    $title = new Translatable(['en' => 'Desk lamp', 'de' => 'Schreibtischlampe']);

    expect($title->get('de-DE'))->toBe('Schreibtischlampe');
});

it('falls back through each shorter prefix of a tag with a script', function () {
    $title = new Translatable(['en' => 'Desk lamp', 'zh-Hans' => '台灯']);

    expect($title->get('zh-Hans-CN'))->toBe('台灯')
        ->and($title->get('zh-Hant'))->toBe('Desk lamp');
});

it('falls back to the default locale of the shop', function () {
    config()->set('larasell.locale.default', 'de');
    config()->set('app.fallback_locale', 'en');
    $title = new Translatable(['en' => 'Desk lamp', 'de' => 'Schreibtischlampe']);

    expect($title->get('fr'))->toBe('Schreibtischlampe');
});

it('falls back to the first translation', function () {
    $title = new Translatable(['de' => 'Schreibtischlampe']);

    expect($title->get('fr'))->toBe('Schreibtischlampe');
});

it('creates updated immutable translations', function () {
    $title = new Translatable(['en' => 'Desk lamp']);
    $translated = $title->with('de', 'Schreibtischlampe');

    expect($title->has('de'))->toBeFalse()
        ->and($translated->has('de'))->toBeTrue()
        ->and($translated->get('de'))->toBe('Schreibtischlampe');
});

it('removes translations immutably', function () {
    $translations = new Translatable(['en' => 'English', 'de' => 'Deutsch']);

    expect($translations->without('de')?->all())->toBe(['en' => 'English'])
        ->and($translations->all())->toBe(['en' => 'English', 'de' => 'Deutsch'])
        ->and($translations->without('en')?->without('de'))->toBeNull();
});

it('stores BCP 47 tags in canonical form', function (string $locale) {
    $title = new Translatable([$locale => 'Schreibtischlampe']);

    expect($title->all())->toBe(['de-DE' => 'Schreibtischlampe'])
        ->and($title->get('de_de'))->toBe('Schreibtischlampe')
        ->and($title->has('DE-de'))->toBeTrue();
})->with(['canonical' => ['de-DE'], 'lowercase region' => ['de-de'], 'Laravel underscore' => ['de_DE']]);

it('resolves the Laravel locale in underscore style', function () {
    App::setLocale('fr_CA');
    $title = new Translatable(['fr' => 'Lampe de bureau', 'fr-CA' => 'Lampe de travail']);

    expect($title->get())->toBe('Lampe de travail');
});

it('accepts script and numeric region subtags', function () {
    $title = new Translatable(['sr-Latn' => 'Stona lampa', 'es-419' => 'Lámpara de escritorio']);

    expect($title->all())->toBe(['sr-Latn' => 'Stona lampa', 'es-419' => 'Lámpara de escritorio']);
});

it('rejects invalid locale identifiers', function (string $locale) {
    new Translatable([$locale => 'Desk lamp']);
})->with(['region before script' => ['zh-CN-Hans'], 'too long language' => ['germ'], 'trailing separator' => ['de-']])
    ->throws(InvalidArgumentException::class);

it('requires at least one non-empty translation', function (array $translations) {
    new Translatable($translations);
})->with([
    'empty payload' => [[]],
    'empty locale' => [['' => 'Desk lamp']],
    'empty value' => [['en' => '']],
])->throws(InvalidArgumentException::class);
