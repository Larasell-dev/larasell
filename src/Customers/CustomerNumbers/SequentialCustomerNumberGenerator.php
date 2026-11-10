<?php

namespace Larasell\Larasell\Customers\CustomerNumbers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use Larasell\Larasell\Contracts\CustomerNumberGenerator;

/**
 * Numbers customers C-000001, C-000002, … Prefix and padding come from larasell.customer_numbers.
 * The number is claimed with one upsert in the customer's transaction, so concurrent creates wait
 * for each other, as order numbers do.
 */
class SequentialCustomerNumberGenerator implements CustomerNumberGenerator
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function generate(): string
    {
        $sequence = $this->db->table('larasell_customer_number_sequences')->where('name', 'customers');
        $sequence->upsert(
            ['name' => 'customers', 'number' => 1],
            ['name'],
            ['number' => new Expression('larasell_customer_number_sequences.number + 1')],
        );
        $number = (string) $sequence->clone()->value('number');

        return config('larasell.customer_numbers.prefix', 'C-')
            .Str::padLeft($number, (int) config('larasell.customer_numbers.padding', 6), '0');
    }
}
