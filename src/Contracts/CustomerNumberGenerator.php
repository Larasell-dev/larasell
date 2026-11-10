<?php

namespace Larasell\Larasell\Contracts;

/** Gives each new customer its number. Bind your own in larasell.customer_numbers.generator. */
interface CustomerNumberGenerator
{
    public function generate(): string;
}
