<?php

namespace Larasell\Larasell\Catalog\Manufacturers\Listeners;

use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Catalog\Manufacturers\AuditRecords\ManufacturerCreated as Created;
use Larasell\Larasell\Catalog\Manufacturers\AuditRecords\ManufacturerDeleted as Deleted;
use Larasell\Larasell\Catalog\Manufacturers\AuditRecords\ManufacturerUpdated as Updated;
use Larasell\Larasell\Catalog\Manufacturers\Events\ManufacturerCreated;
use Larasell\Larasell\Catalog\Manufacturers\Events\ManufacturerDeleted;
use Larasell\Larasell\Catalog\Manufacturers\Events\ManufacturerUpdated;
use Larasell\Larasell\Catalog\Manufacturers\Models\Manufacturer;

/**
 * Audits the core's manufacturer events, in the core action's transaction, so a change that can't be
 * audited rolls back.
 *
 * @internal Registered by LarasellServiceProvider.
 */
class AuditManufacturerChanges
{
    public function __construct(private readonly Auditor $auditor) {}

    public function created(ManufacturerCreated $event): void
    {
        // Read back, so the record says what was stored.
        $manufacturer = Manufacturer::query()->findOrFail($event->manufacturerId->value);

        $this->auditor->record(new Created($event, ['name' => $manufacturer->name, 'country' => $manufacturer->address->country->value]), $event->origin);
    }

    public function updated(ManufacturerUpdated $event): void
    {
        $this->auditor->record(new Updated($event), $event->origin);
    }

    public function deleted(ManufacturerDeleted $event): void
    {
        $this->auditor->record(new Deleted($event), $event->origin);
    }
}
