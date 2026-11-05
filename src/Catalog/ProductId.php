<?php

namespace Larasell\Larasell\Catalog;

use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Ids\Id;

final readonly class ProductId extends Id
{
    protected static function model(): string
    {
        return Product::class;
    }
}
