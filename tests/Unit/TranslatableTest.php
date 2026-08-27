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

    expect($title->get('de_DE'))->toBe('Schreibtischlampe');
});

it('falls back to the configured fallback locale', function () {
    config()->set('app.fallback_locale', 'en');
    $title = new Translatable(['en' => 'Desk lamp', 'de' => 'Schreibtischlampe']);

    expect($title->get('fr'))->toBe('Desk lamp');
});

it('falls back to the first translation', function () {
    config()->set('app.fallback_locale', 'en');
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

it('supports Laravel territory locale identifiers', function () {
    $title = new Translatable(['de_DE' => 'Schreibtischlampe']);

    expect($title->all())->toBe(['de_DE' => 'Schreibtischlampe'])
        ->and($title->get('de_DE'))->toBe('Schreibtischlampe');
});

it('accepts valid locale strings', function () {
    $title = new Translatable(['fr' => 'Lampe de bureau', 'fr_CA' => 'Lampe de travail']);

    expect($title->get('fr'))->toBe('Lampe de bureau')
        ->and($title->get('fr_CA'))->toBe('Lampe de travail');
});

it('rejects invalid locale identifiers', function (string $locale) {
    new Translatable([$locale => 'Desk lamp']);
})->with(['hyphenated territory' => ['de-DE'], 'lowercase territory' => ['de_de']])
    ->throws(\InvalidArgumentException::class);

it('requires at least one non-empty translation', function (array $translations) {
    new Translatable($translations);
})->with([
    'empty payload' => [[]],
    'empty locale' => [['' => 'Desk lamp']],
    'empty value' => [['en' => '']],
])->throws(\InvalidArgumentException::class);
