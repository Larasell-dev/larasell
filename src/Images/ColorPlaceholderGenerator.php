<?php

namespace Larasell\Larasell\Images;

use Larasell\Larasell\Catalog\Models\ProductImage;
use Larasell\Larasell\Contracts\PlaceholderGenerator;

class ColorPlaceholderGenerator implements PlaceholderGenerator
{
    public function generate(ProductImage $image): ?Placeholder
    {
        $raster = Raster::decode($image);

        if ($raster === null) {
            return null;
        }

        $color = Raster::averageColor($raster);
        imagedestroy($raster);

        return new Placeholder('color', $color, $color);
    }
}
