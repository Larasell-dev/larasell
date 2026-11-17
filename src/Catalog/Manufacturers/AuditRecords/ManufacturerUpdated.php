<?php

namespace Larasell\Larasell\Catalog\Manufacturers\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Manufacturers\Events\ManufacturerUpdated as CoreEvent;

/**
 * A manufacturer changed: which fields.
 *
 * @internal Recorded from the core's event. Its event name, manufacturer.updated, is stable.
 */
class ManufacturerUpdated extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'manufacturer.updated';
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject('manufacturer', $this->event->manufacturerId->value);
    }

    public function properties(): array
    {
        return ['changed' => $this->event->changed];
    }
}
