<?php

namespace Larasell\Larasell\Contracts\Promotions;

interface HasRedemptionLimit
{
    public function limit(): int;
}
