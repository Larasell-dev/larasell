<?php

namespace Larasell\Larasell\Catalog\Manufacturers\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Manufacturers\Events\ManufacturerDeleted as CoreEvent;

/**
 * A manufacturer was deleted.
 *
 * @internal Recorded from the core's event. Its event name, manufacturer.deleted, is stable.
 */
class ManufacturerDeleted extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'manufacturer.deleted';
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
        return [];
    }
}
