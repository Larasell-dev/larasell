<?php

namespace Larasell\Larasell\Catalog;

/** The default of an update payload's nullable field: keeps its value, where null clears it. */
enum Keep
{
    case Value;
}
