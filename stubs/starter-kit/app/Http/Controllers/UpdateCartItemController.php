<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Larasell\Larasell\Exceptions\Cart\CartException;

class UpdateCartItemController extends Controller
{
    public function __invoke(Request $request, SessionCart $sessionCart, int $cartItem): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $sessionCart->existing() ?? abort(404);

        try {
            $cart->set($cartItem, $data['quantity']);
        } catch (CartException $exception) {
            throw ValidationException::withMessages([
                'quantity' => $exception->getMessage(),
            ]);
        }

        return back();
    }
}
