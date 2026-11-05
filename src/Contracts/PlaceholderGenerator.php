<?php

namespace Larasell\Larasell\Contracts;

use Larasell\Larasell\Catalog\Models\ProductImage;
use Larasell\Larasell\Images\Placeholder;

interface PlaceholderGenerator
{
    public function generate(ProductImage $image): ?Placeholder;
}
