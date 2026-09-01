<?php

namespace Larasell\Larasell\Database;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/** @internal */
final readonly class TrustedSqlExpression implements Expression
{
    public function __construct(private string $value) {}

    public function getValue(Grammar $grammar): string
    {
        return $this->value;
    }
}
