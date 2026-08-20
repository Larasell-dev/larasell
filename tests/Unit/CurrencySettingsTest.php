<?php

use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Models\Setting;
use Larasell\Larasell\Settings\CurrencySettings;

it('enables only USD by default', function () {
    expect(app(CurrencySettings::class)->enabled())->toBe([Currency::USD])
        ->and(Setting::query()->where('key', 'currencies')->sole()->value)
        ->toBe(['enabled' => ['USD']]);
});

it('does not allow every currency to be disabled', function () {
    app(CurrencySettings::class)->save([]);
})->throws(InvalidArgumentException::class, 'At least one currency must be enabled.');

it('stores enabled currencies as typed settings', function () {
    $settings = app(CurrencySettings::class);

    $settings->save([Currency::EUR, Currency::GBP]);

    expect($settings->enabled())->toBe([Currency::EUR, Currency::GBP])
        ->and(Setting::query()->where('key', 'currencies')->sole()->value)
        ->toBe(['enabled' => ['EUR', 'GBP']]);
});
