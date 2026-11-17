<?php

namespace Larasell\Larasell\Catalog\Manufacturers;

use Larasell\Larasell\Catalog\Manufacturers\Models\Manufacturer;
use Larasell\Larasell\Ids\Id;

final readonly class ManufacturerId extends Id
{
    protected static function model(): string
    {
        return Manufacturer::class;
    }
}
