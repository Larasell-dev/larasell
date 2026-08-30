<?php

namespace Larasell\Larasell\Contracts\Promotions;

use Carbon\CarbonInterface;

interface HasAvailability
{
    /** @return array{starts_at?: CarbonInterface|null, ends_at?: CarbonInterface|null} */
    public function window(): array;
}
