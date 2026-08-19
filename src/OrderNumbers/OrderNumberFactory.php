<?php

namespace Larasell\Larasell\OrderNumbers;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Contracts\OrderNumberGenerator;
use RuntimeException;

class OrderNumberFactory
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly OrderNumberGenerator $generator,
    ) {}

    public function generate(): string
    {
        $sequence = $this->database
            ->table('larasell_order_number_sequences')
            ->insertGetId(['created_at' => now()]);

        $number = $this->generator->generate((int) $sequence);

        if ($number === '') {
            throw new RuntimeException('The order number generator returned an empty order number.');
        }

        return $number;
    }
}
