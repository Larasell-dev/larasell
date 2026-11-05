<?php

namespace Larasell\Larasell\Images;

use Larasell\Larasell\Catalog\Models\ProductImage;
use Larasell\Larasell\Contracts\PlaceholderGenerator;

class LqipPlaceholderGenerator implements PlaceholderGenerator
{
    public function generate(ProductImage $image): ?Placeholder
    {
        $raster = Raster::decode($image);

        if ($raster === null) {
            return null;
        }

        $color = Raster::averageColor($raster);
        $uri = Raster::jpegDataUri($raster);
        imagedestroy($raster);

        if ($uri === null) {
            return new Placeholder('color', $color, $color);
        }

        return new Placeholder('lqip', $uri, $color);
    }
}
