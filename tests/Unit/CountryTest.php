<?php

use Illuminate\Support\Facades\App;
use Larasell\Larasell\Enums\Country;

it('names a country in the given locale, or the app locale', function () {
    App::setLocale('de-DE');

    expect(Country::DE->label())->toBe('Deutschland')
        ->and(Country::CH->label('fr-FR'))->toBe('Suisse')
        ->and(Country::tryFrom('XK'))->toBeNull();
});
