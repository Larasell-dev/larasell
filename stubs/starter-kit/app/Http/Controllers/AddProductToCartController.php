<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Larasell\Larasell\Exceptions\Cart\CartException;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Settings\CurrencySettings;

class AddProductToCartController extends Controller
{
    public function __invoke(Request $request, CurrencySettings $currencies): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);

        try {
            SessionCart::current($currencies)->add($product, $data['quantity']);
        } catch (CartException $exception) {
            return back()->with('message', $exception->getMessage());
        }

        return back();
    }
}
