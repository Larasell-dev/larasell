<?php

namespace Larasell\Larasell\Catalog\Options;

/** How the store shows an option's values for shoppers to pick. */
enum OptionDisplay: string
{
    case Dropdown = 'dropdown';
    case Buttons = 'buttons';
    /** Coloured swatches, from each value's swatch colour. */
    case Swatch = 'swatch';
}
