<?php

namespace Larasell\Larasell\Enums;

enum TaxableLineType: string
{
    case Product = 'product';
    case Shipping = 'shipping';
}
