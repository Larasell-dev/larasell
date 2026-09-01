<?php

namespace Larasell\Larasell\Enums;

enum TaxTreatment: string
{
    case Taxable = 'taxable';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';
    case NotTaxable = 'not_taxable';
}
