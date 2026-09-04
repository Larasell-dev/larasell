<?php

namespace App\Inertia;

use App\Support\SessionCart;

final class CartProp
{
    public function __construct(private readonly SessionCart $sessionCart) {}

    /** @return array{quantity: int} */
    public function prop(): array
    {
        return [
            'quantity' => $this->sessionCart->existing()?->quantity() ?? 0,
        ];
    }
}
