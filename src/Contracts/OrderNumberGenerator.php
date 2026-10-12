<?php

namespace Larasell\Larasell\Contracts;

interface OrderNumberGenerator
{
    public function generate(): string;
}
