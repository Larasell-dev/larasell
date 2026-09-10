<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\ModelRegistry;

class LoginController extends Controller
{
    /**
     * Password authentication for the starter kit. Replace or delete this
     * controller, Auth/Login.tsx, and the login routes to use another
     * auth method. Keep the User model on the StorefrontUser contract and
     * create a Customer record when a user registers.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request, SessionCart $sessionCart, ModelRegistry $models): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $userCart = $models->cart->query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest('id')
            ->first();

        if ($userCart !== null) {
            $sessionCart->mergeInto($userCart);
        }

        return redirect()->intended(route('home'));
    }
}
