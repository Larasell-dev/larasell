<?php

namespace Larasell\Larasell\Catalog;

use Larasell\Larasell\Catalog\Models\ProductImage;
use Larasell\Larasell\Ids\Id;

final readonly class ImageId extends Id
{
    protected static function model(): string
    {
        return ProductImage::class;
    }
}
