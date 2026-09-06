<?php

namespace App\Http\Controllers;

use App\Inertia\CartDetails;
use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Address;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Exceptions\Cart\CartException;
use Larasell\Larasell\Exceptions\Cart\EmptyCartException;
use Larasell\Larasell\Exceptions\Promotions\PromotionException;
use Larasell\Larasell\Taxes\Exceptions\TaxCalculationException;

class CheckoutController extends Controller
{
    public function __construct(private Checkout $checkout) {}

    public function show(SessionCart $sessionCart, CartDetails $cartDetails): RedirectResponse|Response
    {
        $cart = $sessionCart->existing();

        if ($cart === null || $cart->quantity() === 0) {
            return redirect()->route('cart.show');
        }

        return Inertia::render('Checkout/Show', [
            'cart' => $cartDetails->toArray($cart),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, SessionCart $sessionCart): RedirectResponse
    {
        $cart = $sessionCart->existing() ?? abort(404);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'shipping_first_name' => ['required', 'string'],
            'shipping_last_name' => ['required', 'string'],
            'shipping_street' => ['required', 'string'],
            'shipping_city' => ['required', 'string'],
            'shipping_postcode' => ['required', 'string'],
            'shipping_country' => ['required', 'string'],
            'billing_same_as_shipping' => ['required', 'boolean'],
            'billing_first_name' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string'],
            'billing_last_name' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string'],
            'billing_street' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string'],
            'billing_city' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string'],
            'billing_postcode' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string'],
            'billing_country' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ]);

        $shippingAddress = $this->address($data, 'shipping');
        $billingAddress = $request->boolean('billing_same_as_shipping')
            ? $shippingAddress
            : $this->address($data, 'billing');

        try {
            $result = $this->checkout->create($cart, [
                'customer_email' => $data['email'],
                'customer_name' => trim($data['shipping_first_name'].' '.$data['shipping_last_name']),
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
            ], idempotencyKey: $data['idempotency_key']);
        } catch (EmptyCartException) {
            return redirect()->route('cart.show');
        } catch (CartException|TaxCalculationException|PromotionException $exception) {
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

    /** @param array<string, mixed> $data */
    private function address(array $data, string $prefix): Address
    {
        return new Address(
            country: $data["{$prefix}_country"],
            firstName: $data["{$prefix}_first_name"],
            lastName: $data["{$prefix}_last_name"],
            street: $data["{$prefix}_street"],
            city: $data["{$prefix}_city"],
            postcode: $data["{$prefix}_postcode"],
            email: $data['email'],
        );
    }
}
