<?php

namespace Larasell\Larasell\OrderNumbers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use Larasell\Larasell\Contracts\OrderNumberSequence;

abstract class DatabaseOrderNumberSequence implements OrderNumberSequence
{
    public function __construct(private readonly ConnectionInterface $database) {}

    public function next(string $name): int
    {
        $this->assertName($name);

        $start = $this->start();

        // Each sequence is one row holding the last number it issued, claimed with a single statement
        // in the checkout's transaction: its row lock makes concurrent checkouts wait for each other
        // instead of colliding, and a rolled back checkout gives its number back. It is an upsert, not
        // an update followed by an insert, because on MySQL two updates that miss a new sequence's
        // row take gap locks that deadlock both inserts. The column is qualified because PostgreSQL
        // would otherwise not know whether it means the existing row or the one being inserted.
        $sequence = $this->database->table('larasell_order_number_sequences')->where('name', $name);
        $sequence->upsert(
            ['name' => $name, 'number' => $start],
            ['name'],
            ['number' => new Expression('larasell_order_number_sequences.number + 1')],
        );

        // A transaction always reads its own write, even under REPEATABLE READ.
        $next = (int) $sequence->clone()->value('number');

        if ($next < $start) {
            // The start was raised past the sequence; this transaction already holds its row.
            $sequence->update(['number' => $start]);

            return $start;
        }

        return $next;
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
