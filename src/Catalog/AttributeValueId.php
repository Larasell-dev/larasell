<?php

namespace Larasell\Larasell\Catalog;

use Larasell\Larasell\Catalog\Models\ProductAttributeValue;
use Larasell\Larasell\Ids\Id;

final readonly class AttributeValueId extends Id
{
    protected static function model(): string
    {
        return ProductAttributeValue::class;
    }
}
