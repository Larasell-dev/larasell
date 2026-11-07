# Module exceptions moved to `<Module>\Exceptions` and are Chronicle structured errors

Every module now keeps its exceptions in its own `Exceptions` namespace, and
each one implements Chronicle's `StructuredException`, so logs and error pages
get an error code, a why and a fix. Messages are unchanged.

## Who is affected

Apps that import, catch or throw one of these classes:

| Before | After |
| --- | --- |
| `Larasell\Larasell\Ids\InvalidId` | `Larasell\Larasell\Ids\Exceptions\InvalidId` |
| `Larasell\Larasell\SalesChannels\InvalidSalesChannel` | `Larasell\Larasell\SalesChannels\Exceptions\InvalidSalesChannel` |
| `Larasell\Larasell\SalesChannels\SalesChannelInUse` | `Larasell\Larasell\SalesChannels\Exceptions\SalesChannelInUse` |
| `Larasell\Larasell\Seo\InvalidSeoPath` | `Larasell\Larasell\Seo\Exceptions\InvalidSeoPath` |

`InvalidSalesChannel` is now built through named constructors only, e.g.
`InvalidSalesChannel::unknown('b2b')`.

## How to upgrade

```php
// Before
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;

throw new InvalidSalesChannel('There is no sales channel [b2b].');

// After
use Larasell\Larasell\SalesChannels\Exceptions\InvalidSalesChannel;

throw InvalidSalesChannel::unknown('b2b');
```

Update the other imports from the table the same way.
