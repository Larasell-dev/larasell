<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Larasell\Larasell\Exceptions\Promotions\PromotionCodeException;

class ApplyPromotionCodeController extends Controller
{
    public function __invoke(Request $request, SessionCart $sessionCart): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $cart = $sessionCart->existing() ?? abort(404);

        try {
            $cart->applyPromotionCode($data['code']);
        } catch (PromotionCodeException $exception) {
            throw ValidationException::withMessages([
                'code' => $exception->getMessage(),
            ]);
        }

        return back();
    }
}
