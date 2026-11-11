<?php

namespace Larasell\Larasell\Catalog\Options;

use Larasell\Larasell\Catalog\Options\Models\OptionDefinitionValue;
use Larasell\Larasell\Ids\Id;

final readonly class OptionDefinitionValueId extends Id
{
    protected static function model(): string
    {
        return OptionDefinitionValue::class;
    }
}
