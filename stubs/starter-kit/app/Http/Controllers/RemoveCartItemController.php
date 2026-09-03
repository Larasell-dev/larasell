<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Larasell\Larasell\Models\CartItem;

class RemoveCartItemController extends Controller
{
    public function __invoke(CartItem $cartItem): RedirectResponse
    {
        $cart = SessionCart::get();

        // TODO: ideally the cart throws an exception
        abort_unless($cart !== null && $cartItem->cart_id === $cart->getKey(), 404);

        // TODO: Is this the correct way? I think we should do this via the cart itself
        $cartItem->delete();

        return back();
    }
}
