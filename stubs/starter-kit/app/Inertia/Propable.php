<?php

namespace App\Inertia;

interface Propable
{
    /** @return array<array-key, mixed> */
    public function prop(): array;
}
