<?php

namespace Larasell\Larasell\OrderNumbers;

use InvalidArgumentException;

class SingleOrderNumberSequence extends DatabaseOrderNumberSequence
{
    public const string Name = 'default';

    public function next(string $name = self::Name): int
    {
        return parent::next($name);
    }

    protected function assertName(string $name): void
    {
        if ($name !== self::Name) {
            throw new InvalidArgumentException('The single order number sequence only allocates "default".');
        }
    }
}
