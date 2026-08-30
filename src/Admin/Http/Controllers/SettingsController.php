<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        return Inertia::render('Settings/Index', [
            ...$this->layoutProps($admin),
            'membersUrl' => route('larasell.admin.settings.members.index'),
            'currenciesUrl' => route('larasell.admin.settings.currencies.index'),
        ])->rootView('larasell-admin::admin');
    }

    private function layoutProps(object $admin): array
    {
        return [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => ['name' => $admin->name, 'email' => $admin->email],
        ];
    }
}
