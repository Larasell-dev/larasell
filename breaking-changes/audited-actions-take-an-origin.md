# Audited actions take an `Origin`

The core now keeps a tamper-evident audit log (`Larasell\Larasell\Audit`). Every action whose
changes are audited requires an `Origin` as an argument: who made the change, and how it came in
(web, API, command line or job). The core never guesses either, so the audit log names whoever
your app says did the change. The action passes the `Origin` on in its events.

## Who is affected

Apps that call any of these actions, construct or dispatch their events, or call
`SeoUrlManager` directly:

- Catalog: `CreateProduct`, `UpdateProduct`, `DeleteProduct`, `SetProductAttributeValues`,
  `SetProductCategories`, `CreateCategory`, `UpdateCategory`, `MoveCategory`, `DeleteCategory`,
  `DeleteCategoryTree`
- Sales channels and storefronts: `CreateSalesChannel`, `UpdateSalesChannel`,
  `DeleteSalesChannel`, `AddEntryPoint`, `DeleteEntryPoint`
- Customers: every action in `Larasell\Larasell\Customers\Actions`
- Workflows: `DiscontinueProduct`
- SEO: `SeoUrlManager::refresh()`, `refreshPage()`, `setCustom()`, `resetToAutomatic()` and
  `remove()`, `SeoUrlReleased::of()`, and the `BackfillSeoUrls` job

Category changes aren't audited themselves, but they can release another page's old URL, which is.

The events of these actions have a new `public Origin $origin` property, placed before
`$attachments` where an event has them. Apps that only listen to the events aren't affected.

## How to upgrade

Pass an `Origin` after the action's other required arguments:

```php
use Larasell\Larasell\Audit\Actors\SystemActor;
use Larasell\Larasell\Audit\Origin;

// Before
$updateProduct->handle($productId, new UpdateProductPayload(['status' => 'hidden']));
$linkAccount->handle($customerId, $user, $contactId);

// After
$updateProduct->handle($productId, new UpdateProductPayload(['status' => 'hidden']), Origin::web($admin));
$linkAccount->handle($customerId, $user, Origin::web($user), $contactId);
```

The actor is any `Larasell\Larasell\Audit\Actors\Actor`: implement it on your user model, or use
the built-in actors:

```php
Origin::web($user);                                       // a signed-in user's request
Origin::api($apiClient);                                  // an API request
Origin::cli(new SystemActor('shop:import-products'));    // a command
$origin->inJob();                                         // a job, for whoever dispatched it
Origin::cli(new UnknownActor('Imported from the old shop')); // when it truly can't be known
```

A job that acts for whoever dispatched it takes the `Origin` in its constructor and records with
`$this->origin->inJob()`.

Set how long records are kept in `config/larasell/audit.php`, and schedule pruning:

```php
Schedule::command('larasell:prune-audit-log')->daily();
```

Run the new migrations, which create the `larasell_audit_log` tables. Apps that already have them from larasell-dev/admin keep them as they are; the migrations have the same names, so they don't run again:

```sh
php artisan migrate
```
