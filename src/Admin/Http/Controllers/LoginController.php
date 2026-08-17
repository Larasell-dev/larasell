<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Login', [
            'loginUrl' => route('larasell.admin.login'),
        ])->rootView('larasell-admin::admin');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = config('larasell-admin.guard', 'larasell-admin');

        if (! Auth::guard($guard)->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route(config('larasell-admin.home', 'larasell.admin.home')));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $guard = config('larasell-admin.guard', 'larasell-admin');

        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('larasell.admin.login');
    }
}
