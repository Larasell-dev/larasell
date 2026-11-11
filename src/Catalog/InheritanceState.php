<?php

namespace Larasell\Larasell\Catalog;

/** Whether a variant takes a field's value from its product or has its own. */
enum InheritanceState: string
{
    /** The product's value. */
    case Inherited = 'inherited';

    /** The variant's own value, which may be empty: then the variant has none, whatever the product has. */
    case Overridden = 'overridden';
}
