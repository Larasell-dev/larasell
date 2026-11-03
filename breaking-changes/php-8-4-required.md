# Larasell requires PHP 8.4

Larasell now needs PHP 8.4 or newer. Chronicle, the logging package Larasell is built on, requires
PHP 8.4 from version 0.3.0, and Larasell's CI already runs on PHP 8.4 only, so PHP 8.3 was
untested.

## Who is affected

Apps that run on PHP 8.3. Composer won't install this version of Larasell there. Apps on PHP 8.4
or 8.5 are not affected.

## How to upgrade

Upgrade PHP to 8.4 or newer on every server and in CI, then raise the PHP requirement in your
app's `composer.json`:

```json
// Before
"require": {
    "php": "^8.3"
}

// After
"require": {
    "php": "^8.4"
}
```
