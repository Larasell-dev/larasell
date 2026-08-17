<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'logoutUrl' => route('larasell.admin.logout'),
        ])->rootView('larasell-admin::admin');
    }
}
