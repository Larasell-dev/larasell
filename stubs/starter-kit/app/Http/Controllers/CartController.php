<?php

namespace App\Http\Controllers;

use App\Inertia\CartDetails;
use App\Support\SessionCart;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __invoke(SessionCart $sessionCart, CartDetails $cartDetails): Response
    {
        $cart = $sessionCart->existing();

        return Inertia::render('Cart/Show', [
            'cart' => $cart === null ? null : $cartDetails->toArray($cart),
        ]);
    }
}
