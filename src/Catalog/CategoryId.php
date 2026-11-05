<?php

namespace Larasell\Larasell\Catalog;

use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Ids\Id;

final readonly class CategoryId extends Id
{
    protected static function model(): string
    {
        return Category::class;
    }
}
