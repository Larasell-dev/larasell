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

    /**
     * Find the session cart, creating one if needed.
     *
     * Use only when adding a line. Reads, updates, checkout, and the cart
     * page should call existing() so browsing does not insert empty carts.
     */
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
        $userId = $this->userId();
        $cart = $this->fromSession();
        $userCart = $userId === null ? null : $this->forUser($userId);

        if ($cart !== null && $this->ownedByAnotherUser($cart, $userId)) {
            $this->request->session()->forget(self::SESSION_KEY);
            $cart = null;
        }

        if ($cart !== null && $userCart !== null && $cart->isNot($userCart)) {
            $cart = $userCart;
            $this->request->session()->put(self::SESSION_KEY, $cart->getKey());
        }

        if ($cart === null && $userCart !== null) {
            $cart = $userCart;
            $this->request->session()->put(self::SESSION_KEY, $cart->getKey());
        }

        if ($cart !== null && $userId !== null && $cart->user_id === null) {
            $cart->forceFill(['user_id' => $userId])->save();
        }

        return $this->cart = $cart;
    }

    public function remember(Cart $cart): Cart
    {
        $this->request->session()->put(self::SESSION_KEY, $cart->getKey());
        $this->cart = $cart;
        $this->resolved = true;

        return $cart;
    }

    public function mergeInto(Cart $destination): Cart
    {
        $source = $this->fromSession();

        if ($source === null || $source->is($destination)) {
            return $this->remember($destination);
        }

        if ($source->user_id !== null && $source->user_id !== $destination->user_id) {
            return $this->remember($destination);
        }

        return $this->remember($destination->merge($source)->cart);
    }

    /**
     * Delete the session cart and drop the session pointer.
     */
    public function forget(): void
    {
        $this->existing()?->delete();

        $this->request->session()->forget(self::SESSION_KEY);
        $this->cart = null;
        $this->resolved = true;
    }

    private function create(): Cart
    {
        /** @var Cart $cart */
        $cart = $this->models->cart->query()->create([
            'currency' => $this->currencies->enabled()[0],
            'user_id' => $this->userId(),
        ]);

        $this->request->session()->put(self::SESSION_KEY, $cart->getKey());
        $this->cart = $cart;
        $this->resolved = true;

        return $cart;
    }

    private function fromSession(): ?Cart
    {
        $cartId = $this->request->session()->get(self::SESSION_KEY);

        if (! is_int($cartId)) {
            return null;
        }

        /** @var Cart|null $cart */
        $cart = $this->models->cart->query()->find($cartId);

        if ($cart === null) {
            $this->request->session()->forget(self::SESSION_KEY);
        }

        return $cart;
    }

    private function forUser(int $userId): ?Cart
    {
        /** @var Cart|null $cart */
        $cart = $this->models->cart->query()
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        return $cart;
    }

    private function ownedByAnotherUser(Cart $cart, ?int $userId): bool
    {
        return $userId !== null
            && $cart->user_id !== null
            && $cart->user_id !== $userId;
    }

    private function userId(): ?int
    {
        $id = $this->request->user()?->getAuthIdentifier();

        if (is_int($id)) {
            return $id;
        }

        if (is_string($id) && ctype_digit($id)) {
            return (int) $id;
        }

        return null;
    }
}
