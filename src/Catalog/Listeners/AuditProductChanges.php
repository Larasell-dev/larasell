<?php

namespace Larasell\Larasell\Catalog\Listeners;

use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Catalog\AuditRecords\ProductCreated as Created;
use Larasell\Larasell\Catalog\AuditRecords\ProductDeleted as Deleted;
use Larasell\Larasell\Catalog\AuditRecords\ProductUpdated as Updated;
use Larasell\Larasell\Catalog\Events\ProductCreated;
use Larasell\Larasell\Catalog\Events\ProductDeleted;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Models\Product;

/**
 * Audits the core's product events, in the core action's transaction, so a change that can't be
 * audited rolls back.
 *
 * @internal Registered by LarasellServiceProvider.
 */
class AuditProductChanges
{
    public function __construct(private readonly Auditor $auditor) {}

    public function created(ProductCreated $event): void
    {
        // Read back, so the record says what was stored.
        $name = Product::query()->findOrFail($event->productId->value)->name;

        $this->auditor->record(new Created($event, $name->all()), $event->origin);
    }

    public function updated(ProductUpdated $event): void
    {
        if ($event->changed === []) {
            return;
        }

        $this->auditor->record(new Updated($event), $event->origin);
    }

    public function deleted(ProductDeleted $event): void
    {
        $this->auditor->record(new Deleted($event), $event->origin);
    }
}
