<?php

namespace App\Support;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Http\Request;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Settings\CurrencySettings;

#[Scoped]
final class SessionCart
{
    private const SESSION_KEY = 'larasell.cart_id';

    private ?Cart $cart = null;

    private bool $resolved = false;

    public function __construct(
        private readonly Request $request,
        private readonly ModelRegistry $models,
        private readonly CurrencySettings $currencies,
    ) {}

    public function get(): Cart
    {
        return $this->existing() ?? $this->create();
    }

    public function existing(): ?Cart
    {
        if ($this->resolved) {
            return $this->cart;
        }

        $this->resolved = true;
        $cartId = $this->request->session()->get(self::SESSION_KEY);

        if (! is_int($cartId)) {
            return null;
        }

        /** @var Cart|null $cart */
        $cart = $this->models->cart->query()->find($cartId);

        if ($cart === null) {
            $this->request->session()->forget(self::SESSION_KEY);
        }

        return $this->cart = $cart;
    }

    public function forget(): void
    {
        $this->request->session()->forget(self::SESSION_KEY);
        $this->cart = null;
        $this->resolved = true;
    }

    private function create(): Cart
    {
        /** @var Cart $cart */
        $cart = $this->models->cart->query()->create([
            'currency' => $this->currencies->enabled()[0],
        ]);

        $this->request->session()->put(self::SESSION_KEY, $cart->getKey());
        $this->cart = $cart;
        $this->resolved = true;

        return $cart;
    }
}
