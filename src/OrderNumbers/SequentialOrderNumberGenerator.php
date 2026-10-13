<?php

namespace Larasell\Larasell\OrderNumbers;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Larasell\Larasell\Contracts\OrderNumberGenerator;

class SequentialOrderNumberGenerator implements OrderNumberGenerator
{
    private const int DefaultPadding = 6;

    private const string DefaultPrefix = 'LS-';

    public function __construct(private readonly SingleOrderNumberSequence $sequences) {}

    public function generate(): string
    {
        $prefix = (string) config('larasell.order_numbers.prefix', self::DefaultPrefix);
        $suffix = (string) config('larasell.order_numbers.suffix');
        $padding = config('larasell.order_numbers.padding', self::DefaultPadding);

        if ($padding !== null && filter_var($padding, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new InvalidArgumentException('The order number padding must be a positive integer or null.');
        }

        $sequence = $this->sequences->next();
        $number = (string) $sequence;

        if ($padding !== null) {
            $number = Str::padLeft($number, (int) $padding, '0');
        }

        return $prefix.$number.$suffix;
    }
}
