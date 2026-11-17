<?php

namespace Larasell\Larasell\Catalog\Brands\Listeners;

use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Catalog\Brands\AuditRecords\BrandCreated as Created;
use Larasell\Larasell\Catalog\Brands\AuditRecords\BrandDeleted as Deleted;
use Larasell\Larasell\Catalog\Brands\AuditRecords\BrandUpdated as Updated;
use Larasell\Larasell\Catalog\Brands\Events\BrandCreated;
use Larasell\Larasell\Catalog\Brands\Events\BrandDeleted;
use Larasell\Larasell\Catalog\Brands\Events\BrandUpdated;
use Larasell\Larasell\Catalog\Brands\Models\Brand;

/**
 * Audits the core's brand events, in the core action's transaction, so a change that can't be
 * audited rolls back.
 *
 * @internal Registered by LarasellServiceProvider.
 */
class AuditBrandChanges
{
    public function __construct(private readonly Auditor $auditor) {}

    public function created(BrandCreated $event): void
    {
        // Read back, so the record says what was stored.
        $brand = Brand::query()->findOrFail($event->brandId->value);

        $this->auditor->record(new Created($event, ['slug' => $brand->slug, 'name' => $brand->name->all()]), $event->origin);
    }

    public function updated(BrandUpdated $event): void
    {
        $this->auditor->record(new Updated($event), $event->origin);
    }

    public function deleted(BrandDeleted $event): void
    {
        $this->auditor->record(new Deleted($event), $event->origin);
    }
}
