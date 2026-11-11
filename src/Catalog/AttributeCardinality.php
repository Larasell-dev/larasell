<?php

namespace Larasell\Larasell\Catalog;

/** How many values of an attribute a product or variant can have. */
enum AttributeCardinality: string
{
    /** One value at most, e.g. a fit. */
    case Single = 'single';

    /** Any number of values, e.g. the materials of a blend. A variant's own values replace the product's whole list. */
    case Multiple = 'multiple';
}
