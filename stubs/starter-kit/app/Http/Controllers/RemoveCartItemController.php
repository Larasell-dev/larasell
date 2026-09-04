<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;

class RemoveCartItemController extends Controller
{
    public function __invoke(SessionCart $sessionCart, int $cartItem): RedirectResponse
    {
        $cart = $sessionCart->existing() ?? abort(404);
        $cart->remove($cartItem);

        return back();
    }
}
