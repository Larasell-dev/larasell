<?php

namespace Larasell\Larasell\Contracts\Promotions;

interface HasRedemptionLimit
{
    /** @return int|array{global?: int, customer?: int} */
    public function limit(): int|array;
}
