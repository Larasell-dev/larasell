<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RemovePromotionCodeController extends Controller
{
    public function __invoke(Request $request, SessionCart $sessionCart): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $cart = $sessionCart->existing() ?? abort(404);
        $cart->removePromotionCode($data['code']);

        return back();
    }
}
