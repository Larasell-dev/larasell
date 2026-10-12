<?php

namespace Larasell\Larasell\OrderNumbers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use Larasell\Larasell\Contracts\OrderNumberSequence;

abstract class DatabaseOrderNumberSequence implements OrderNumberSequence
{
    public function __construct(private readonly ConnectionInterface $database) {}

    public function next(string $name): int
    {
        $this->assertName($name);

        $start = $this->start();

        while (true) {
            $highest = $this->database->table('larasell_order_number_sequences')
                ->where('name', $name)
                ->max('number');
            $next = max($start, (int) $highest + 1);

            try {
                $this->database->table('larasell_order_number_sequences')->insert([
                    'name' => $name,
                    'number' => $next,
                    'created_at' => now(),
                ]);

                return $next;
            } catch (UniqueConstraintViolationException) {
                // Another checkout claimed this number.
            }
        }
    }

    abstract protected function assertName(string $name): void;

    private function start(): int
    {
        $start = config('larasell.order_numbers.start', 1);

        if ($start === null) {
            return 1;
        }

        if (is_int($start) && $start >= 1) {
            return $start;
        }

        if (is_string($start) && preg_match('/\A[1-9]\d*\z/', $start) === 1) {
            return (int) $start;
        }

        throw new InvalidArgumentException('The order number start must be a positive integer.');
    }
}
