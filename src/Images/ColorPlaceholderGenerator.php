<?php

namespace Larasell\Larasell\Images;

use Larasell\Larasell\Contracts\PlaceholderGenerator;
use Larasell\Larasell\Models\ProductImage;

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
