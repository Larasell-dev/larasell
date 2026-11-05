<?php

namespace Larasell\Larasell\Catalog;

use Larasell\Larasell\Catalog\Models\ProductVariant;
use Larasell\Larasell\Ids\Id;

final readonly class VariantId extends Id
{
    protected static function model(): string
    {
        return ProductVariant::class;
    }
}
