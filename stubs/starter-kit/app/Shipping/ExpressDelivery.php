<?php

namespace App\Shipping;

use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingMethod;

final class ExpressDelivery extends ShippingMethod
{
    public function handle(Cart $cart): void
    {
        $this->register('express', 'Express delivery', Price::of(1200));
    }
}
