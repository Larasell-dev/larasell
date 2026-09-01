<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Settings\CurrencySettings;

class CurrencySettingsController extends Controller
{
    public function index(Request $request, CurrencySettings $settings): Response
    {
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));
        $enabled = $settings->enabled();

        return Inertia::render('Settings/Currencies/Index', [
            ...$this->layoutProps($admin),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'updateUrl' => route('larasell.admin.settings.currencies.update'),
            'currencies' => array_map(fn (Currency $currency): array => [
                'code' => $currency->value,
                'enabled' => in_array($currency, $enabled, true),
            ], Currency::cases()),
        ])->rootView('larasell-admin::admin');
    }

    public function update(Request $request, CurrencySettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['required', 'string', 'distinct', Rule::enum(Currency::class)],
        ]);

        $settings->save(array_map(fn (string $code): Currency => Currency::from($code), $data['currencies']));

        return redirect()->route('larasell.admin.settings.currencies.index');
    }

    private function layoutProps(object $admin): array
    {
        return [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->getAttribute('name'),
                'email' => $admin->getAttribute('email'),
            ],
        ];
    }
}
