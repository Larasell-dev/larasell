<?php

namespace Larasell\Larasell\Customers\Enums;

/** Where a customer was first created. */
enum CustomerSource: string
{
    case Storefront = 'storefront';
    case Pos = 'pos';
    case Admin = 'admin';
    case Import = 'import';
    case Api = 'api';
}
