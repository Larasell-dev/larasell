<?php

namespace Larasell\Larasell\Catalog;

/** Where a variant takes the value of a field it can inherit from its product. */
enum InheritanceState: string
{
    /** The product's value. */
    case Inherited = 'inherited';

    /** The variant's own value. */
    case Overridden = 'overridden';

    /** No value, even though the product has one. */
    case Cleared = 'cleared';
}
