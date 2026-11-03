# Larasell uses Chronicle 0.4

Larasell's structured exceptions, `LicenseException` and `UnknownEntitlement`, now implement
Chronicle 0.4's `Larasell\Chronicle\Exceptions\StructuredException` instead of
`Larasell\Chronicle\StructuredError`. Chronicle 0.4 bounds and redacts every event it logs and adds
a fake for tests, and Larasell needs it so apps and the admin panel can upgrade too.

## Who is affected

- Apps that catch Larasell's exceptions as `Larasell\Chronicle\StructuredError`.
- Apps that use Chronicle themselves, e.g. its `LogRequests` middleware or structured exceptions.
  Composer now installs Chronicle 0.4, so follow
  [Chronicle's upgrade guide](https://github.com/Larasell-dev/chronicle/blob/main/CHANGELOG.md).

Apps that catch `LicenseException` or `UnknownEntitlement` by name, or only read `errorCode()`,
`errorWhy()`, `errorFix()` and `errorLink()`, are not affected.

## How to upgrade

Catch the new interface:

```php
// Before
use Larasell\Chronicle\StructuredError;

try {
    $license->activate($key);
} catch (StructuredError $exception) {
    report($exception);
}

// After
use Larasell\Chronicle\Exceptions\StructuredException;

try {
    $license->activate($key);
} catch (StructuredException $exception) {
    report($exception);
}
```
