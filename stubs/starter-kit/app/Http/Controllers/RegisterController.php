<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\ModelRegistry;

class RegisterController extends Controller
{
    /**
     * Password registration for the starter kit. Replace or delete this
     * controller, Auth/Register.tsx, and the register routes to use another
     * auth method. Keep the User model on the StorefrontUser contract and
     * create a Customer record when a user registers.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, SessionCart $sessionCart, ModelRegistry $models): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create($data);
        $user->createCustomer();

        Auth::login($user);
        $request->session()->regenerate();
        $userCart = $models->cart->query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest('id')
            ->first();

        if ($userCart !== null) {
            $sessionCart->mergeInto($userCart);
        }

        return redirect()->route('home');
    }
}
