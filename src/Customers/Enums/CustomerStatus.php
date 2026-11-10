<?php

namespace Larasell\Larasell\Customers\Enums;

/** Customers are blocked or archived rather than deleted, so their history stays intact. */
enum CustomerStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Archived = 'archived';
}
