<?php

namespace Larasell\Larasell\Contracts;

interface OrderNumberGenerator
{
    public function generate(int $sequence): string;
}
