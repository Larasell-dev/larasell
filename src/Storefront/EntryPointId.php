<?php

namespace Larasell\Larasell\Storefront;

use Larasell\Larasell\Ids\Id;
use Larasell\Larasell\Storefront\Models\EntryPoint;

final readonly class EntryPointId extends Id
{
    protected static function model(): string
    {
        return EntryPoint::class;
    }
}
