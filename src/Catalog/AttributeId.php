<?php

namespace Larasell\Larasell\Catalog;

use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Ids\Id;

final readonly class AttributeId extends Id
{
    protected static function model(): string
    {
        return ProductAttribute::class;
    }
}
