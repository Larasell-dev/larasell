<?php

namespace Larasell\Larasell\Images;

use Larasell\Larasell\Catalog\Models\ProductImage;
use Larasell\Larasell\Contracts\PlaceholderGenerator;

class NullPlaceholderGenerator implements PlaceholderGenerator
{
    public function generate(ProductImage $image): ?Placeholder
    {
        return null;
    }
}
