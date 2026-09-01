<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use ResolvesAdminUser;

    public function __invoke(Request $request): Response
    {
        $admin = $this->adminUser($request);

        return Inertia::render('Settings/Index', [
            ...$this->layoutProps($admin),
            'membersUrl' => route('larasell.admin.settings.members.index'),
            'currenciesUrl' => route('larasell.admin.settings.currencies.index'),
        ])->rootView('larasell-admin::admin');
    }

    /** @return array<string, mixed> */
    private function layoutProps(Model $admin): array
    {
        return [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->getAttribute('name'),
                'email' => $admin->getAttribute('email'),
            ],
        ];
    }
}
