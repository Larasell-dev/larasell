<?php

namespace Larasell\Larasell\Contracts;

interface OrderNumberSequence
{
    public function next(string $name): int;
}
