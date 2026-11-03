<?php

namespace Larasell\Larasell\Exceptions\Licensing;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

class UnknownEntitlement extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function undefined(string $name): static
    {
        return static::structured(
            errorCode: 'larasell.licensing.unknown-entitlement',
            message: "The entitlement [{$name}] is not defined.",
            why: 'Checking an entitlement nothing defined would always look inactive, which hides a typo as "not entitled".',
            fix: "Define it first, e.g. Entitlement::define('{$name}', Plan::Pro), or check the spelling.",
        );
    }
}
