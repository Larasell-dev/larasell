<?php

namespace Larasell\Larasell\OrderNumbers;

use InvalidArgumentException;

class MultipleOrderNumberSequence extends DatabaseOrderNumberSequence
{
    protected function assertName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('An order number sequence name is required.');
        }
    }
}
