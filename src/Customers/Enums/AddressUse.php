<?php

namespace Larasell\Larasell\Customers\Enums;

/** What a default address is the default for. */
enum AddressUse: string
{
    case Billing = 'billing';
    case Shipping = 'shipping';
}
