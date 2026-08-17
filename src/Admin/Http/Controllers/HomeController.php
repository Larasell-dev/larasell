<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        return Inertia::render('Home', [
            'homeUrl' => route('larasell.admin.home'),
            'productsUrl' => route('larasell.admin.products.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ])->rootView('larasell-admin::admin');
    }
}
