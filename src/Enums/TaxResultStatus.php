<?php

namespace Larasell\Larasell\Enums;

enum TaxResultStatus: string
{
    case Calculated = 'calculated';
    case Provisional = 'provisional';
    case Unavailable = 'unavailable';
}
