<?php

namespace Larasell\Larasell\Shipping;

/** The product capabilities Shipping owns. */
final class ShippingCapabilities
{
    /** The product is shipped, so carts with it need a shipping method. */
    public const SHIPPING = 'shipping';

    /** The product has a weight and dimensions. */
    public const DIMENSIONS = 'dimensions';
}
