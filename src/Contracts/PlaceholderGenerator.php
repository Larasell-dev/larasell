<?php

namespace Larasell\Larasell\Contracts;

use Larasell\Larasell\Images\Placeholder;
use Larasell\Larasell\Models\ProductImage;

interface PlaceholderGenerator
{
    public function generate(ProductImage $image): ?Placeholder;
}
