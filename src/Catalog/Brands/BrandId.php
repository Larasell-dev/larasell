<?php

namespace Larasell\Larasell\Catalog\Brands;

use Larasell\Larasell\Catalog\Brands\Models\Brand;
use Larasell\Larasell\Ids\Id;

final readonly class BrandId extends Id
{
    protected static function model(): string
    {
        return Brand::class;
    }
}
