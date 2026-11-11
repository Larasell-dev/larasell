<?php

namespace Larasell\Larasell\Catalog\Options;

use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Ids\Id;

final readonly class OptionDefinitionId extends Id
{
    protected static function model(): string
    {
        return OptionDefinition::class;
    }
}
