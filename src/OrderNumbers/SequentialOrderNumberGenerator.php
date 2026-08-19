<?php

namespace Larasell\Larasell\OrderNumbers;

use Larasell\Larasell\Contracts\OrderNumberGenerator;

class SequentialOrderNumberGenerator implements OrderNumberGenerator
{
    public function generate(int $sequence): string
    {
        $prefix = (string) config('larasell.order_numbers.prefix', 'LS-');
        $padding = max(0, (int) config('larasell.order_numbers.padding', 6));

        return $prefix.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
    }
}
