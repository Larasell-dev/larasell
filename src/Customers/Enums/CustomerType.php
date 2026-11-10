<?php

namespace Larasell\Larasell\Customers\Enums;

/** What kind of identity a customer is: one person, or a company with contacts. */
enum CustomerType: string
{
    case Individual = 'individual';
    case Company = 'company';
}
