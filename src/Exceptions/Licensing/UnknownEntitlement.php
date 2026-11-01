<?php

namespace Larasell\Larasell\Exceptions\Licensing;

use InvalidArgumentException;
use Larasell\Chronicle\HasStructuredError;
use Larasell\Chronicle\StructuredError;

class UnknownEntitlement extends InvalidArgumentException implements StructuredError
{
    use HasStructuredError;

    public static function undefined(string $name): static
    {
        return static::structured(
            code: 'larasell.licensing.unknown-entitlement',
            message: "The entitlement [{$name}] is not defined.",
            why: 'Checking an entitlement nothing defined would always look inactive, which hides a typo as "not entitled".',
            fix: "Define it first, e.g. Entitlement::define('{$name}', Plan::Pro), or check the spelling.",
        );
    }
}
