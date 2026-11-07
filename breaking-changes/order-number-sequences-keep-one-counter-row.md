# `larasell_order_number_sequences` keeps one counter row per sequence

The table used to get a new row for every order number issued. Concurrent
checkouts read the highest number, tried to insert the next one and retried on a
collision, which deadlocked on MySQL and MariaDB and failed outright on
PostgreSQL. Now each sequence is a single row holding the last number it
issued, and `SingleOrderNumberSequence` and `MultipleOrderNumberSequence` claim
the next number by incrementing that row inside the checkout's transaction.
Concurrent checkouts wait for each other on that row instead of colliding.

The PHP API is unchanged: `OrderNumberSequence::next()` still returns the next
number, honours `larasell.order_numbers.start` the same way, and order numbers
continue where they left off. A checkout that rolls back now gives its number
back, so numbers no longer skip after a failed checkout.

## Who is affected

Apps that read or write `larasell_order_number_sequences` themselves, for
example to count issued numbers or to look up when a number was issued. Its
`id` and `created_at` columns are gone, and it holds one row per sequence name
with the last number in `number`. Apps that only use the sequences or order
numbers through Larasell are not affected; run the migration.

## How to upgrade

Run the migration. It keeps the highest issued number of every sequence, so the
next order number follows the last one:

```sh
php artisan migrate
```

Read a sequence's last number from its row instead of from the newest row:

```php
// Before
DB::table('larasell_order_number_sequences')->where('name', 'default')->max('number');
DB::table('larasell_order_number_sequences')->where('name', 'default')->count();

// After
DB::table('larasell_order_number_sequences')->where('name', 'default')->value('number');
```

The issued numbers themselves are on the orders, in `larasell_orders.number`.
