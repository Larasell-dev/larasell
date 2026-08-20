<?php

use Illuminate\Support\Facades\Hash;
use Larasell\Larasell\Admin\Models\AdminUser;
use Larasell\Larasell\Models\Setting;

function currencySettingsAdmin(): AdminUser
{
    return AdminUser::query()->create([
        'name' => 'Larasell Admin',
        'email' => 'currency-admin@example.com',
        'password' => Hash::make('password'),
    ]);
}

it('shows USD as the only enabled currency by default', function () {
    $this->actingAs(currencySettingsAdmin(), 'larasell-admin')
        ->withHeader('X-Inertia', 'true')
        ->get(route('larasell.admin.settings.currencies.index'))
        ->assertOk()
        ->assertJsonPath('component', 'Settings/Currencies/Index')
        ->assertJsonPath('props.currencies.0.code', 'USD')
        ->assertJsonPath('props.currencies.0.enabled', true)
        ->assertJsonPath('props.currencies.1.enabled', false);
});

it('updates enabled currencies', function () {
    $this->actingAs(currencySettingsAdmin(), 'larasell-admin')
        ->patch(route('larasell.admin.settings.currencies.update'), [
            'currencies' => ['EUR', 'GBP'],
        ])
        ->assertRedirect(route('larasell.admin.settings.currencies.index'));

    expect(Setting::query()->where('key', 'currencies')->sole()->value)
        ->toBe(['enabled' => ['EUR', 'GBP']]);
});

it('requires at least one valid currency', function () {
    $admin = currencySettingsAdmin();

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.settings.currencies.update'), ['currencies' => []])
        ->assertSessionHasErrors('currencies');

    $this->actingAs($admin, 'larasell-admin')
        ->patch(route('larasell.admin.settings.currencies.update'), ['currencies' => ['BTC']])
        ->assertSessionHasErrors('currencies.0');

    expect(Setting::query()->where('key', 'currencies')->sole()->value)
        ->toBe(['enabled' => ['USD']]);
});
