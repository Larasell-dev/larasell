<?php

namespace Larasell\Larasell\Enums;

enum TaxPriceMode: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';
}
