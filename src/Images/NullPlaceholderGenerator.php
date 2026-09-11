<?php

namespace Larasell\Larasell\Images;

use Larasell\Larasell\Contracts\PlaceholderGenerator;
use Larasell\Larasell\Models\ProductImage;

class NullPlaceholderGenerator implements PlaceholderGenerator
{
    public function generate(ProductImage $image): ?Placeholder
    {
        return null;
    }
}
