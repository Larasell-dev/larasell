<?php

namespace Larasell\Larasell\Catalog;

/** What an attribute describes: the product, each variant, or the product with variants inheriting it. */
enum AttributeScope: string
{
    /** Only the product has a value. */
    case Product = 'product';

    /** Only variants have values. */
    case Variant = 'variant';

    /** The product has a value, which variants inherit, override or clear. */
    case Inherited = 'inherited';
}
