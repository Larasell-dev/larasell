<?php

namespace Larasell\Larasell\Enums;

enum ProductAttributeType: string
{
    case Boolean = 'boolean';
    case Number = 'number';
    case Text = 'text';

    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::Boolean => is_bool($value),
            self::Number => is_int($value) || is_float($value),
            self::Text => is_string($value),
        };
    }
}
