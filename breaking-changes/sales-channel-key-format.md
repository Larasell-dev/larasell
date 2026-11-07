# `CreateSalesChannel` checks the channel key

`CreateSalesChannel` now refuses a key that isn't lowercase letters and numbers joined by `-` or
`_` (e.g. `b2b-shop`), is longer than 64 characters, or another channel already has. It throws
`InvalidSalesChannel`, like for its other checks. Code names a channel by its key, as in
`SalesContext::for('b2b-shop')`, so the rule belongs with the channel rather than with each
form that creates one. A duplicate key used to fail with a database `QueryException`.

## Who is affected

Apps that call `Larasell\Larasell\SalesChannels\Actions\CreateSalesChannel` with a key in
capitals, with spaces or other characters, or that catch `QueryException` for a duplicate key.
Existing channels are not checked or changed; apps that already use keys like `default` or
`b2b-shop` can skip this entry.

## How to upgrade

Pass a key in the new format, and catch `InvalidSalesChannel` for a duplicate:

```php
use Larasell\Larasell\SalesChannels\Actions\CreateSalesChannel;
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;

// Before
try {
    app(CreateSalesChannel::class)->handle(['key' => 'B2B Shop', /* … */]);
} catch (\Illuminate\Database\QueryException) {
    // the key was taken
}

// After
try {
    app(CreateSalesChannel::class)->handle(['key' => 'b2b-shop', /* … */]);
} catch (InvalidSalesChannel $exception) {
    // the key is taken or malformed; $exception->getMessage() says which
}
```
