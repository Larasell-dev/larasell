<?php

namespace App\Shipping;

use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingMethod;

final class StandardDelivery extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('standard', 'Standard delivery', Price::of(500));
    }
}
