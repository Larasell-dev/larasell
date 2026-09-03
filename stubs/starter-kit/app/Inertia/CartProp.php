<?php

namespace App\Inertia;

use App\Support\SessionCart;

final class CartProp implements Propable
{
    /**
     * @return array{quantity: int}
     */
    public function prop(): array
    {
        $cart = SessionCart::get();

        return [
            'quantity' => $cart?->quantity() ?? 0,
        ];
    }
}
