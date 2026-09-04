<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Address;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Exceptions\Cart\CartException;
use Larasell\Larasell\Exceptions\Cart\EmptyCartException;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Price;
use Larasell\Larasell\Taxes\Exceptions\TaxCalculationException;

class CheckoutController extends Controller
{
    public function __construct(private Checkout $checkout) {}

    public function show(SessionCart $sessionCart): RedirectResponse|Response
    {
        $cart = $sessionCart->existing();

        if ($cart === null || $cart->quantity() === 0) {
            return redirect()->route('cart.show');
        }

        $locale = App::currentLocale();
        $items = $cart->purchasableItems();
        $subtotal = $cart->subtotal();
        $total = $cart->total();

        return Inertia::render('Checkout/Show', [
            'cart' => [
                'items' => $items->map(fn (CartItem $item): array => [
                    'id' => $item->getKey(),
                    'name' => $item->product->name->get(),
                    'quantity' => $item->quantity,
                    'unitPrice' => Price::format($item->unitPrice(), $cart->currency, $locale),
                    'total' => Price::format($item->total(), $cart->currency, $locale),
                ])->all(),
                'quantity' => $cart->quantity(),
                'subtotal' => $subtotal === null ? null : Price::format($subtotal, $cart->currency, $locale),
                'total' => $total === null ? null : Price::format($total, $cart->currency, $locale),
            ],
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, SessionCart $sessionCart): RedirectResponse
    {
        $cart = $sessionCart->existing() ?? abort(404);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'street' => ['required', 'string'],
            'city' => ['required', 'string'],
            'postcode' => ['required', 'string'],
            'country' => ['required', 'string'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ]);

        $address = new Address(
            country: $data['country'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            street: $data['street'],
            city: $data['city'],
            postcode: $data['postcode'],
            email: $data['email'],
        );

        try {
            $result = $this->checkout->create($cart, [
                'customer_email' => $data['email'],
                'customer_name' => trim($data['first_name'].' '.$data['last_name']),
                'billing_address' => $address,
                'shipping_address' => $address,
            ], idempotencyKey: $data['idempotency_key']);
        } catch (EmptyCartException) {
            return redirect()->route('cart.show');
        } catch (CartException|TaxCalculationException $exception) {
            throw ValidationException::withMessages([
                'checkout' => $exception->getMessage(),
            ]);
        }

        $sessionCart->forget();

        if ($result->requiresRedirect()) {
            return $result->redirect();
        }

        return redirect()->route('orders.confirmation', $result->order->public_id);
    }
}
